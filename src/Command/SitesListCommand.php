<?php

namespace Samwilson\MediaWikiCLI\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SitesListCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'sites:list' );
		$this->setDescription( $this->msg( 'command-sites-list-desc' ) );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		parent::execute( $input, $output );
		$config = $this->getConfig( $input );
		$headers = [ 'ID', 'Name', 'API' ];
		$rows = [];
		foreach ( $config['sites'] as $site ) {
			$rows[] = [
				$site['id'],
				$site['name'],
				$site['api_url']
			];
		}
		$this->io->table( $headers, $rows );
	}
}
