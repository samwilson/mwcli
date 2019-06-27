<?php

namespace Samwilson\MediaWikiCLI\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Mediawiki\Api\FluentRequest;
use Mediawiki\Api\MediawikiApi;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportContribsCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'export:contribs' );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addOption( 'user', 'u', InputOption::VALUE_REQUIRED, $this->msg( 'option-user-desc' ) );
		$this->addOption( 'dest', 'd', InputOption::VALUE_REQUIRED, $this->msg( 'option-dest-desc' ),
			dirname( __DIR__, 2 ) . '/contribs' );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$ret = parent::execute( $input, $output );
		if ( $ret ) {
			return $ret;
		}
		$config = $this->getConfig( $input );

		$wiki = $input->getOption( 'wiki' );

		$site = false;
		if ( isset( $config['sites'] ) ) {
			foreach ( $config['sites'] as $i => $s ) {
				if ( $s['id'] === $wiki ) {
					$site = $s;
				}
			}
		}
		if ( !$site ) {
			$this->io->warning( $this->msg( 'sites-auth-sitenotfound' ) );
			return 1;
		}

		$api = MediawikiApi::newFromPage( $site['api_url'] );
		$continue = true;
		$siteinfoReq = FluentRequest::factory()->setAction( 'query' )
			->setParam( 'list', 'usercontribs' )
			->setParam( 'ucuser', $input->getOption( 'user' ) );
		while ( $continue ) {
			$contribs = $api->getRequest( $siteinfoReq );
			if ( isset( $contribs['continue']['uccontinue'] ) ) {
				$siteinfoReq->setParam( 'uccontinue', $contribs['continue']['uccontinue'] );
			} else {
				// Last page of contribs.
				$continue = false;
			}
			$destDirBase = $input->getOption( 'dest' );
			$this->getPageOfContribs( $site, $destDirBase, $contribs, $api );
		}
		return 0;
	}

	public function getPageOfContribs( $site, $destDirBase, $contribs, MediawikiApi $api ) {
		$client = new Client();
		$requests = [];
		foreach ( $contribs['query']['usercontribs'] as $contrib ) {
			// Figure out namespace name.
			$firstColon = strpos( $contrib['title'], ':' );
			$namespace = $firstColon ? substr( $contrib['title'], 0, $firstColon ) : '(main)';
			$pageTitlePart = $firstColon ? substr( $contrib['title'], $firstColon + 1 ) : $contrib['title'];
			$pageTitle = str_replace( '/', '%2F', str_replace( ' ', '_', $pageTitlePart ) );

			// XML export file of the page.
			$xmlFile = $destDirBase . '/' . $site['id'] . '/pages/' . $namespace . '/' . $pageTitle . '.xml';
			if ( !is_dir( dirname( $xmlFile ) ) ) {
				$this->io->writeln( 'Creating directory ' . dirname( $xmlFile ) );
				mkdir( dirname( $xmlFile ), 0755, true );
			}
			$uri = str_replace( 'api.php', 'index.php', $site['api_url'] )
				. '?title=Special:Export&pages=' . $contrib['title'] . '&history=1';
			$requests[$namespace . ':' . $pageTitle] = function () use ( $client, $uri, $xmlFile ) {
				return $client->postAsync( $uri, [ 'sink' => $xmlFile ] );
			};

			// File export for file pages.
			if ( $namespace === 'File' ) {
				$imageInfoRequest = FluentRequest::factory()->setAction( 'query' )
					->setParam( 'prop', 'imageinfo' )
					->setParam( 'iiprop', 'url' )
					->setParam( 'pageids', $contrib['pageid'] );
				$imageInfoResponse = $api->getRequest( $imageInfoRequest );
				$imageInfo = $imageInfoResponse['query']['pages'][$contrib['pageid']];
				if ( isset( $imageInfo['imageinfo'] ) ) {
					// Not all file pages have images (e.g. redirects).
					$fileUrl = $imageInfo['imageinfo'][0]['url'];
					$destFile = $destDirBase . '/' . $site['id'] . '/files/' . basename( $fileUrl );
					if ( !is_dir( dirname( $destFile ) ) ) {
						$this->io->writeln( 'Creating directory ' . dirname( $destFile ) );
						mkdir( dirname( $destFile ), 0755, true );
					}
					$requests[$namespace . ':' . $pageTitle] = function () use ( $client, $fileUrl, $destFile ) {
						return $client->getAsync( $fileUrl, [ 'sink' => $destFile ] );
					};
				}
			}
		}
		$pool = new Pool( $client, $requests, [
			// 'concurrency' => 5,
			'fulfilled' => function ( $response, $index ) {
				$this->io->writeln( $index );
			},
		] );
		$promise = $pool->promise();
		$promise->wait();
	}
}
