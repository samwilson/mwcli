<?php

namespace Samwilson\MediaWikiCLI\Command;

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Service\NamespaceGetter;
use DirectoryIterator;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UploadPagesCommand extends CommandBase {

	/** @var string */
	protected $dir;

	/** @var string[] File extensions that will be stripped from uploaded page titles. */
	protected $ignoredFileExtensions = [ 'txt', 'wikitext', 'md', 'lua' ];

	/** @var ActionApi */
	protected $api;

	/** @var string[][] */
	protected $namespaces;

	/** @var bool */
	protected $watch;

	/** @var string[] */
	protected $watches;

	/** @var resource */
	protected $inotify;

	/** @var string */
	protected $comment;

	public function configure() {
		parent::configure();
		$this->setName( 'upload:pages' );
		$this->setDescription( $this->msg( 'command-upload-pages-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addOption( 'comment', 'm', InputOption::VALUE_OPTIONAL, $this->msg( 'option-comment-desc' ) );
		$this->addOption( 'watch', 't', InputOption::VALUE_NONE, $this->msg( 'option-watch-desc' ) );
		$this->addArgument( 'pages-dir', InputArgument::REQUIRED, $this->msg( 'arg-pages-dir-desc' ) );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$ret = parent::execute( $input, $output );
		if ( $ret ) {
			return $ret;
		}
		$dir = $input->getArgument( 'pages-dir' );
		if ( empty( $dir ) ) {
			$this->io->warning( $this->msg( 'no-directory' ) );
			return 1;
		}
		if ( !is_dir( $dir ) ) {
			$this->io->warning( $this->msg( 'not-a-directory', [ $dir ] ) );
			return 1;
		}
		$this->dir = realpath( $dir );
		$this->comment = $input->getOption( 'comment' );
		$this->watch = $input->getOption( 'watch' ) && function_exists( 'inotify_init' );
		if ( $this->watch ) {
			$this->inotify = inotify_init();
		}
		// API.
		$site = $this->getSite( $input );
		$this->api = $this->getApi( $site, $this->getAuthMethod( $input ) );

		// Namespaces (ignore mainspace).
		$nsGetter = new NamespaceGetter( $this->api );
		$this->namespaces = [];
		foreach ( $nsGetter->getNamespaces() as $ns ) {
			if ( empty( $ns->getCanonicalName() ) ) {
				continue;
			}
			$namespaces = array_merge( [ $ns->getCanonicalName(), $ns->getLocalName() ], $ns->getAliases() );
			$this->namespaces[] = array_unique( array_map( 'strtolower', $namespaces ) );
		}

		$this->import( $this->dir );

		if ( $this->watch ) {
			$this->io->writeln( $this->msg( 'now-watching', [ $this->dir ] ) );
			while ( true ) {
				$events = inotify_read( $this->inotify );
				foreach ( $events as $event ) {
					$file = $this->watches[ $event['wd'] ];
					$this->importPage( $file );
				}
			}
		}

		return 0;
	}

	/**
	 * @param string $dir The directory to import. Will recurse on subdirectories.
	 */
	public function import( $dir ) {
		$topLevel = new DirectoryIterator( $dir );
		foreach ( $topLevel as $file ) {
			if ( $file->isDot() ) {
				continue;
			}
			if ( $file->isDir() ) {
				$this->import( "$dir/$file" );
			} else {
				$this->importPage( $file->getPathname() );
			}
		}
	}

	protected function importPage( $filename ) {
		$pageTitle = ucfirst( substr( $filename, strlen( $this->dir ) + 1 ) );
		$fileExtension = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( in_array( $fileExtension, $this->ignoredFileExtensions ) ) {
			$pageTitle = substr( $pageTitle, 0, -( strlen( $fileExtension ) + 1 ) );
		}
		if ( strpos( $pageTitle, '#' ) !== false ) {
			$this->io->warning( $this->msg( 'page-title-contains-hash', [ $filename ] ) );
			return;
		}

		// Normalize namespace if first-level directory is a namespace name.
		$slashPos = strpos( $pageTitle, '/' );
		if ( $slashPos !== false ) {
			$firstPart = substr( $pageTitle, 0, $slashPos );
			$latterParts = substr( $pageTitle, $slashPos + 1 );
			foreach ( $this->namespaces as $nsForms ) {
				if ( in_array( str_replace( '_', ' ', strtolower( $firstPart ) ), $nsForms ) ) {
					$pageTitle = $firstPart . ':' . ucfirst( $latterParts );
				}
			}
		}

		// Watch this file if required.
		if ( $this->watch ) {
			$watchId = inotify_add_watch( $this->inotify, $filename, IN_MODIFY );
			$this->watches[ $watchId ] = $filename;
		}
		$contents = file_get_contents( $filename );
		$editParams = [
			'text' => $contents,
			'md5' => md5( $contents ),
			'title' => $pageTitle,
			'token' => $this->api->getToken(),
			'summary' => $this->comment,
		];
		try {
			$result = $this->api->request( ActionRequest::simplePost( 'edit', $editParams ) );
		} catch ( Exception $exception ) {
			// Show the error, but carry on with other pages.
			$this->io->error( $pageTitle . ' ' . $exception->getMessage() );
			return;
		}
		if ( $result['edit']['result'] !== 'Success' ) {
			$this->io->warning( $result['edit']['result'] );
		}
		if ( isset( $result['edit']['nochange'] ) && !$this->watch ) {
			// Only if we're not watching should we report unchanged pages.
			$this->io->writeln( $this->msg( 'page-not-changed', [ $result['edit']['title'] ] ) );
		}
		if ( !isset( $result['edit']['nochange'] ) ) {
			$this->io->writeln( $this->msg( 'uploaded-page', [ $result['edit']['newtimestamp'], $result['edit']['title'] ] ) );
		}
	}
}
