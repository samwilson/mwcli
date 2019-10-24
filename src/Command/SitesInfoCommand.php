<?php

namespace Samwilson\MediaWikiCLI\Command;

use Mediawiki\Api\FluentRequest;
use Mediawiki\Api\MediawikiApi;
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

		$api = MediawikiApi::newFromApiEndpoint( $site['api_url'] );
		$siteinfoReq = FluentRequest::factory()->setAction( 'query' )
			->setParam( 'meta', 'siteinfo' )
			->setParam( 'siprop', 'general|statistics|extensions' );
		$siteInfo = $api->getRequest( $siteinfoReq );

		$general = $siteInfo['query']['general'];

		// Version. It's constructed in includes/api/ApiQuerySiteinfo.php with: "MediaWiki {$config->get( 'Version' )}"
		preg_match( '/MediaWiki ([\d.]+)/', $general['generator'], $matches );
		$version = $matches[1];
		$this->io->writeln( 'MediaWiki: ' . $version );
		return 0;
	}
}
