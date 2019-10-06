<?php

namespace Samwilson\MediaWikiCLI\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SitesRemoveCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'sites:remove' );
		$this->setDescription( $this->msg( 'command-sites-remove-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		parent::execute( $input, $output );
		$config = $this->getConfig( $input );
		$wiki = $input->getOption( 'wiki' );
		if ( !$wiki ) {
			$this->io->warning( $this->msg( 'sites-remove-nowiki' ) );
			return 1;
		}
		$siteRemoved = false;
		if ( isset( $config['sites'] ) ) {
			foreach ( $config['sites'] as $index => $site ) {
				if ( $site['id'] === $wiki ) {
					$siteRemoved = true;
					unset( $config['sites'][$index] );
				}
			}
		}
		if ( $siteRemoved ) {
			$this->saveConfig( $input, $config );
			$this->io->block( $this->msg( 'sites-add-removed' ) );
		}
	}
}
