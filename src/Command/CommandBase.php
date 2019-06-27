<?php

namespace Samwilson\MediaWikiCLI\Command;

use Krinkle\Intuition\Intuition;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

abstract class CommandBase extends Command {

	/** @var Intuition */
	protected $intuition;

	/** @var SymfonyStyle */
	protected $io;

	public function configure() {
		// Set up i18n.
		$this->intuition = new Intuition( 'mwcli' );
		$this->intuition->registerDomain( 'mwcli', dirname( __DIR__, 2 ) . '/i18n' );

		$default = $this->getConfigDir() . 'config.yml';
		$this->addOption( 'config', 'c', InputOption::VALUE_OPTIONAL, $this->msg( 'option-config-desc' ), $default );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$this->io = new SymfonyStyle( $input, $output );

		// 'wiki' is a common option for many commands, so we can validate it here.
		if ( $input->hasOption( 'wiki' ) && empty( $input->getOption( 'wiki' ) ) ) {
			$this->io->note( $this->msg( 'no-wiki-specified' ) );
			return 1;
		}
	}

	/**
	 * Get a localized message.
	 *
	 * @param string $msg The message to get.
	 * @param string[] $vars The message variables.
	 * @return string the Localized message.
	 */
	protected function msg( string $msg, ?array $vars = [] ): string {
		return $this->intuition->msg( $msg, [
			'domain' => 'mwcli',
			'variables' => $vars,
		] );
	}

	/**
	 * @return string The full filesystem path to the directory containing config.yml, always with a trailing slash.
	 */
	protected function getConfigDir(): string {
		// Use the current working directory for the config file,
		// but that can fail on some systems so we fall back to the script's directory.
		$configDir = getcwd();
		if ( false === $configDir ) {
			$configDir = dirname( __DIR__, 2 );
		}
		return rtrim( $configDir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * @param InputInterface $input
	 * @return mixed[][]
	 */
	protected function getConfig( InputInterface $input ): array {
		$configPath = $input->getOption( 'config' );
		if ( !file_exists( $configPath ) ) {
			// Create an empty config file.
			$this->saveConfig( $input, [] );
		}
		$this->io->block( $this->msg( 'using-config', [ $configPath ] ) );
		$config = Yaml::parseFile( $configPath );
		return $config;
	}

	/**
	 * Save a new config, completely replacing what exists (if any).
	 * @param InputInterface $input
	 * @param string[] $config
	 */
	protected function saveConfig( InputInterface $input, array $config ): void {
		$configPath = $input->getOption( 'config' );
		file_put_contents( $configPath, Yaml::dump( $config ) );
		$this->io->success( $this->msg( 'saved-config', [ $configPath ] ) );
	}
}
