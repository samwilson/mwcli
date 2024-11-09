<?php

namespace Samwilson\MediaWikiCLI\Command;

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Addwiki\Mediawiki\Api\Service\CategoryTraverser;
use Addwiki\Mediawiki\Api\Service\NamespaceGetter;
use Addwiki\Mediawiki\DataModel\Page;
use Addwiki\Mediawiki\DataModel\PageIdentifier;
use Addwiki\Mediawiki\DataModel\Title;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCategoryCommand extends CommandBase {

	/** @var string Destination directory. */
	private $optionDest;

	/** @var ActionApi */
	private ActionApi $api;

	public function configure() {
		parent::configure();
		$this->setName( 'export:category' );
		$this->setDescription( $this->msg( 'command-export-category-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addOption( 'category', 'a', InputOption::VALUE_REQUIRED, $this->msg( 'option-category-desc' ) );
		$this->addOption( 'dest', 'd', InputOption::VALUE_REQUIRED, $this->msg( 'option-dest-desc' ),
			$this->getConfigDirDefault() . 'categories' );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$ret = parent::execute( $input, $output );
		if ( $ret ) {
			return $ret;
		}
		$siteInfo = $this->getSite( $input );
		if ( !$siteInfo ) {
			return Command::FAILURE;
		}
		$this->api = $this->getApi( $siteInfo );
		$catTraverser = ( new MediawikiFactory( $this->api ) )->newCategoryTraverser();
		$catTraverser->addCallback( CategoryTraverser::CALLBACK_PAGE, [ $this, 'descender' ] );
		$catTraverser->addCallback( CategoryTraverser::CALLBACK_CATEGORY, [ $this, 'descender' ] );

		// The category option can be with or without the namespace prefix, and it can be given as any of its aliases.
		$categoryName = $input->getOption( 'category' );
		if ( !$categoryName ) {
			$this->io->warning( 'Please set the --category option.' );
			return Command::FAILURE;
		}
		$catAliases = ( new NamespaceGetter( $this->api ) )
			->getNamespaceByName( 'Category' )
			->getAliases();
		$catPrefixes = array_merge( [ 'Category' ], $catAliases );
		foreach ( $catPrefixes as $catPrefix ) {
			if ( str_starts_with( $categoryName, $catPrefix . ':' ) ) {
				$categoryName = substr( $categoryName, strlen( $catPrefix ) + 1 );
			}
		}
		$categoryNamespaceId = 14;
		$catTitle = new Title( 'Category:' . ucfirst( $categoryName ), $categoryNamespaceId );
		$this->io->writeln( 'Downloading ' . $catTitle->getText() );

		$this->optionDest = $input->getOption( 'dest' ) . '/' . $siteInfo['id'];

		$catProps = [
			'titles' => $catTitle->getText(),
			'action' => 'query',
			'prop' => 'info',
			'formatversion' => 2,
			'inprop' => 'url',
		];
		$cat = $this->api->request( ActionRequest::simpleGet( 'query', $catProps ) );
		$catInfo = reset( $cat['query']['pages'] );
		if ( isset( $catInfo['missing'] ) ) {
			$this->io->error( $this->msg( 'export-cat-not-found', [ $catInfo['canonicalurl'] ] ) );
			return Command::FAILURE;
		}
		$catTraverser->descend( new Page( new PageIdentifier( $catTitle ) ) );
		return Command::SUCCESS;
	}

	public function descender( Page $member, Page $rootCat ) {
		$title = $member->getPageIdentifier()->getTitle()->getText();

		// Sparate namespace and page names.
		$firstColon = strpos( $title, ':' );
		$namespace = $firstColon ? substr( $title, 0, $firstColon ) : '(main)';
		$pageTitlePart = $firstColon ? substr( $title, $firstColon + 1 ) : $title;
		$pageTitle = str_replace( ' ', '_', $pageTitlePart );

		$this->io->writeln( "Downloading $title . . . " );
		$pageInfo = $this->api->request( ActionRequest::simpleGet( 'query', [
			'prop' => 'imageinfo|revisions',
			'iiprop' => 'url|sha1|timestamp',
			'titles' => $title,
			'rvprop' => 'content',
			'rvslots' => 'main|mediainfo',
			'formatversion' => 2,
		] ) );

		if ( !isset( $pageInfo['query']['pages'] ) ) {
			echo "Unable to get $title\n";
			exit();
		}
		$page = array_shift( $pageInfo['query']['pages'] );

		// File.
		if ( isset( $page['imageinfo'] ) ) {
			$fileUrl = $page['imageinfo'][0]['url'];
			$destFile = $this->optionDest . '/files/' . basename( $fileUrl );
			if ( !is_file( $destFile ) || sha1_file( $destFile ) !== $page['imageinfo'][0]['sha1'] ) {
				if ( !is_dir( dirname( $destFile ) ) ) {
					$this->io->writeln( 'Creating directory ' . dirname( $destFile ) );
					mkdir( dirname( $destFile ), 0755, true );
				}
				$this->io->writeln( "    File: $destFile" );
				( new Client() )->get( $fileUrl, [ 'sink' => $destFile ] );
			}
		}

		// Wikitext of the page.
		$destWikitext = $this->optionDest . '/' . $namespace . '/' . $pageTitle . '.wikitext';
		$rev = reset( $page['revisions'] );
		$content = $rev['slots']['main']['content'];
		if ( !empty( trim( $content ) ) ) {
			if ( !is_dir( dirname( $destWikitext ) ) ) {
				$this->io->writeln( 'Creating directory: ' . dirname( $destWikitext ) );
				mkdir( dirname( $destWikitext ), 0755, true );
			}
			$this->io->writeln( "    Wikitext: $destWikitext" );
			file_put_contents( $destWikitext, $content );
		}

		// MediaInfo JSON.
		if ( isset( $rev['slots']['mediainfo']['content'] ) ) {
			$destMediaInfo = $this->optionDest . '/' . $namespace . '/' . $pageTitle . '_mediainfo.json';
			$this->io->writeln( "    Structured data: $destMediaInfo" );
			file_put_contents( $destMediaInfo, $rev['slots']['mediainfo']['content'] );
		}
	}
}
