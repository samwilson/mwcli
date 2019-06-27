<?php

namespace Samwilson\MediaWikiCLI\Command;

use Mediawiki\Api\FluentRequest;
use Mediawiki\Api\MediawikiApi;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SitesAddCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'sites:add' );
		$this->addOption( 'url', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-url-desc' ) );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		parent::execute( $input, $output );
		$url = $input->getOption( 'url' );
		if ( !$url ) {
			$url = $this->io->ask( $this->msg( 'sites-add-ask-url' ) );
		}
		$api = MediawikiApi::newFromPage( $url );
		$siteinfoReq = FluentRequest::factory()->setAction( 'query' )->setParam( 'meta', 'siteinfo' );
		$siteInfo = $api->getRequest( $siteinfoReq );

		$config = $this->getConfig( $input );
		if ( !isset( $config['sites'] ) ) {
			$config['sites'] = [];
		}
		$newSite = [
			'id' => $siteInfo['query']['general']['wikiid'],
			'name' => $siteInfo['query']['general']['sitename'],
			'main_page_url' => $siteInfo['query']['general']['base'],
			'api_url' => $api->getApiUrl(),
		];
		$config['sites'][] = $newSite;
		$this->saveConfig( $input, $config );

		$this->io->block( $this->msg( 'sites-add-added', [ $newSite['name'], $newSite['main_page_url'] ] ) );
	}
}
