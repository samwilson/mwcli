<?php

namespace Samwilson\MediaWikiCLI\Command;

use Mediawiki\Api\FluentRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SitesInfoCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'sites:info' );
		$this->setDescription( $this->msg( 'command-sites-info-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
	}

	/**
	 * @param InputInterface $input An InputInterface instance
	 * @param OutputInterface $output An OutputInterface instance
	 *
	 * @return null|int null or 0 if everything went fine, or an error code
	 */
	public function execute( InputInterface $input, OutputInterface $output ) {
		$ret = parent::execute( $input, $output );
		if ( $ret ) {
			return $ret;
		}
		$site = $this->getSite( $input );
		if ( !$site ) {
			return 1;
		}
		$api = $this->getApi( $site );

		$this->logger->debug( 'Getting siteinfo from ' . $api->getApiUrl() );
		$siteinfoReq = FluentRequest::factory()->setAction( 'query' )
			->setParam( 'meta', 'siteinfo' )
			->setParam( 'siprop', 'general|statistics|extensions' );
		$siteInfo = $api->getRequest( $siteinfoReq );

		$general = $siteInfo['query']['general'];
		$this->logger->debug( 'General info', $general );

		// Version. It's constructed in includes/api/ApiQuerySiteinfo.php with: "MediaWiki {$config->get( 'Version' )}"
		preg_match( '/MediaWiki ([\d.]+)/', $general['generator'], $matches );
		$version = $matches[1];
		$this->io->definitionList(
			[ 'Version:' => $version ],
			[ 'API URL:' => $api->getApiUrl() ]
		);

		if ( isset( $siteInfo['query']['statistics'] ) ) {
			$this->io->section( 'Statistics' );
			array_walk( $siteInfo['query']['statistics'], static function ( &$v, $k ) { $v = [ $k, $v ];
			} );
			$this->io->table( [], $siteInfo['query']['statistics'] );
		}

		// Installed extensions.
		$this->io->section( 'Extensions' );
		$extensions = [];
		foreach ( $siteInfo['query']['extensions'] ?? [] as $extension ) {
			$extensions[] = [
				$extension['name'],
				$extension['version'] ?? '',
			];
		}
		$this->io->table( [ 'Name', 'Version' ], $extensions );

		return 0;
	}
}
