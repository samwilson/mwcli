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
		$this->setDescription( $this->msg( 'command-export-contribs-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addOption( 'user', 'u', InputOption::VALUE_REQUIRED, $this->msg( 'option-user-desc' ) );
		$this->addOption( 'dest', 'd', InputOption::VALUE_REQUIRED, $this->msg( 'option-dest-desc' ),
			$this->getConfigDirDefault() . 'contribs' );
		$this->addOption( 'only-author', 'o', InputOption::VALUE_NONE, $this->msg( 'option-only-author-desc' ) );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$ret = parent::execute( $input, $output );
		if ( $ret ) {
			return $ret;
		}
		$site = $this->getSite( $input );
		if ( !$site ) {
			return 1;
		}

		$user = $input->getOption( 'user' );
		if ( !$user ) {
			$this->io->error( $this->msg( 'option-user-missing' ) );
			return 1;
		}

		$api = MediawikiApi::newFromApiEndpoint( $site['api_url'] );
		$continue = true;
		$siteinfoReq = FluentRequest::factory()->setAction( 'query' )
			->setParam( 'list', 'usercontribs' )
			->setParam( 'ucuser', $user );
		while ( $continue ) {
			$contribs = $api->getRequest( $siteinfoReq );
			if ( isset( $contribs['continue']['uccontinue'] ) ) {
				$siteinfoReq->setParam( 'uccontinue', $contribs['continue']['uccontinue'] );
			} else {
				// Last page of contribs.
				$continue = false;
			}
			$destDirBase = $input->getOption( 'dest' );
			$onlyAuthor = $input->getOption( 'only-author' );
			$this->getPageOfContribs( $site, $destDirBase, $contribs, $api, $onlyAuthor );
		}
		return 0;
	}

	public function getPageOfContribs( $site, $destDirBase, $contribs, MediawikiApi $api, bool $onlyAuthor ) {
		$client = new Client();
		$requests = [];
		foreach ( $contribs['query']['usercontribs'] as $contrib ) {
			// See if we're only getting author revisions.
			if ( $onlyAuthor && $contrib['parentid'] > 0 ) {
				continue;
			}

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
			$requests['XML  -- ' . $namespace . ':' . $pageTitle] = function () use ( $client, $uri, $xmlFile ) {
				return $client->postAsync( $uri, [ 'sink' => $xmlFile ] );
			};

			// File export for file pages.
			if ( $namespace === 'File' ) {
				$imageInfoRequest = FluentRequest::factory()->setAction( 'query' )
					->setParam( 'prop', 'imageinfo' )
					->setParam( 'iiprop', 'url|sha1' )
					->setParam( 'pageids', $contrib['pageid'] );
				$imageInfoResponse = $api->getRequest( $imageInfoRequest );
				$imageInfo = $imageInfoResponse['query']['pages'][$contrib['pageid']];
				// Not all file pages have images (e.g. redirects).
				if ( isset( $imageInfo['imageinfo'] ) ) {
					$fileUrl = $imageInfo['imageinfo'][0]['url'];
					$destFile = $destDirBase . '/' . $site['id'] . '/files/' . basename( $fileUrl );
					// See if the file already exists and is of the right version.
					if ( is_file( $destFile ) && sha1_file( $destFile ) === $imageInfo['imageinfo'][0]['sha1'] ) {
						continue;
					}
					if ( !is_dir( dirname( $destFile ) ) ) {
						$this->io->writeln( 'Creating directory ' . dirname( $destFile ) );
						mkdir( dirname( $destFile ), 0755, true );
					}
					$requests['File -- ' . $namespace . ':' . $pageTitle] = function () use ( $client, $fileUrl, $destFile ) {
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
