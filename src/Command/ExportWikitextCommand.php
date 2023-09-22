<?php

namespace Samwilson\MediaWikiCLI\Command;

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Service\NamespaceGetter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportWikitextCommand extends CommandBase {

	/** @var string Destination directory. */
	private $optionDest;

	/** @var string File extension. */
	private $optionExt;

	/** @var ActionApi */
	private $api;

	public function configure() {
		parent::configure();
		$this->setName( 'export:wikitext' );
		$this->setDescription( $this->msg( 'command-export-wikitext-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addOption( 'dest', 'd', InputOption::VALUE_REQUIRED, $this->msg( 'option-dest-desc' ),
			$this->getConfigDirDefault() . 'wikitext' );
		$this->addOption( 'ext', 'e', InputOption::VALUE_REQUIRED, $this->msg( 'option-ext-desc' ), 'txt' );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$ret = parent::execute( $input, $output );
		if ( $ret ) {
			return $ret;
		}
		$site = $this->getSite( $input );
		if ( !$site ) {
			return Command::FAILURE;
		}
		$this->optionDest = $input->getOption( 'dest' ) . '/' . $site['id'];
		$this->optionExt = $input->getOption( 'ext' );
		$this->api = $this->getApi( $site );
		$nsGetter = new NamespaceGetter( $this->api );
		foreach ( $nsGetter->getNamespaces() as $ns ) {
			if ( $ns->getId() < 0 ) {
				continue;
			}
			$this->getAllPagesInNamespace( $ns->getId() );
		}
		return Command::SUCCESS;
	}

	private function getAllPagesInNamespace( $nsId ) {
		$continue = true;
		$allpagesRequest = ActionRequest::simpleGet( 'query', [
			'prop' => 'revisions',
			'generator' => 'allpages',
			'gapnamespace' => $nsId,
			'gapfilterredir' => 'nonredirects',
			'rvprop' => 'content',
			'rvslots' => 'main',
			'formatversion' => 2
		] );
		while ( $continue ) {
			$result = $this->api->request( $allpagesRequest );
			if ( isset( $result['continue']['gapcontinue'] ) ) {
				$allpagesRequest->setParam( 'gapcontinue', $result['continue']['gapcontinue'] );
			} else {
				// Last page of results.
				$continue = false;
			}
			if ( isset( $result['query']['pages'] ) ) {
				$this->getBatchOfPages( $result['query']['pages'] );
			}
		}
	}

	private function getBatchOfPages( array $pages ) {
		foreach ( $pages as $page ) {
			$title = $page['title'];
			$rev = reset( $page['revisions'] );
			$content = $rev['slots']['main']['content'];
			if ( empty( trim( $content ) ) ) {
				continue;
			}

			// Sparate namespace and page names.
			$firstColon = strpos( $title, ':' );
			$namespace = $firstColon ? substr( $title, 0, $firstColon ) : '(main)';
			$pageTitlePart = $firstColon ? substr( $title, $firstColon + 1 ) : $title;
			$pageTitle = str_replace( ' ', '_', $pageTitlePart );

			// XML export file of the page.
			$filename = $this->optionDest . '/' . $namespace . '/' . $pageTitle . '.' . $this->optionExt;
			if ( !is_dir( dirname( $filename ) ) ) {
				$this->io->writeln( 'Creating directory: ' . dirname( $filename ) );
				mkdir( dirname( $filename ), 0755, true );
			}

			$this->io->writeln( 'Writing file: ' . $filename );
			file_put_contents( $filename, $content );
		}
	}
}
