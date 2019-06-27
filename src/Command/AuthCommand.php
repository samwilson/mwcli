<?php

namespace Samwilson\MediaWikiCLI\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AuthCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'auth' );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		parent::execute( $input, $output );
		$config = $this->getConfig( $input );

		$wiki = $input->getOption( 'wiki' );
		if ( !$wiki ) {
			$this->io->warning( $this->msg( 'sites-auth-nowiki' ) );
			return 1;
		}

		$authSite = false;
		$siteFound = false;
		if ( isset( $config['sites'] ) ) {
			foreach ( $config['sites'] as $index => $site ) {
				if ( $site['id'] === $wiki ) {
					$siteFound = true;
					$authSite = $site;
				}
			}
		}

		if ( !$siteFound ) {
			$this->io->warning( $this->msg( 'sites-auth-sitenotfound' ) );
			return 1;
		}

		dd( $authSite );
	}
}
