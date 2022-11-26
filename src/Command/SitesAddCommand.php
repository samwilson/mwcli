<?php

namespace Samwilson\MediaWikiCLI\Command;

use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Client\MediaWiki;
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
		/** @var Aci */
		$api = MediaWiki::newFromPage( $url );
		$siteinfoReq = ActionRequest::simpleGet( 'query', [ 'meta' => 'siteinfo' ] );
		$siteInfo = $api->action()->request( $siteinfoReq );

		$newSite = [
			'name' => $siteInfo['query']['general']['sitename'],
			'main_page_url' => $siteInfo['query']['general']['base'],
			'api_url' => $api->action()->getApiUrl(),
		];
		$this->setSite( $input, $siteInfo['query']['general']['wikiid'], $newSite );

		$this->io->block( $this->msg( 'sites-add-added', [ $newSite['name'], $newSite['main_page_url'] ] ) );
		return 0;
	}
}
