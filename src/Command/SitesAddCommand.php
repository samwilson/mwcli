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
		$this->setDescription( $this->msg( 'command-sites-add-desc' ) );
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

		$newSite = [
			'name' => $siteInfo['query']['general']['sitename'],
			'main_page_url' => $siteInfo['query']['general']['base'],
			'api_url' => $api->getApiUrl(),
		];
		$this->setSite( $input, $siteInfo['query']['general']['wikiid'], $newSite );

		$this->io->block( $this->msg( 'sites-add-added', [ $newSite['name'], $newSite['main_page_url'] ] ) );
		return 0;
	}
}
