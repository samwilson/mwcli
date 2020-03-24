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
		if ( !isset( $config['sites'] ) ) {
			$this->io->block( $this->msg( 'no-sites-found' ) );
			return 0;
		}
		$headers = [ 'ID', 'Name', 'API', 'Install path' ];
		$rows = [];
		foreach ( $config['sites'] as $siteId => $site ) {
			$rows[] = [
				$siteId,
				$site['name'],
				empty( $site['api_url'] ) ? '' : '✓',
				empty( $site['install_path'] ) ? '' : '✓',
			];
		}
		$this->io->table( $headers, $rows );
	}
}
