<?php

namespace Samwilson\MediaWikiCLI\Command;

use Exception;
use Krinkle\Intuition\Intuition;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
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

	/** @var LoggerInterface */
	protected $logger;

	public function configure() {
		// Set up i18n.
		$this->intuition = new Intuition( 'mwcli' );
		$this->intuition->registerDomain( 'mwcli', dirname( __DIR__, 2 ) . '/i18n' );

		$default = $this->getConfigDirDefault() . 'config.yml';
		$this->addOption( 'config', 'c', InputOption::VALUE_OPTIONAL, $this->msg( 'option-config-desc' ), $default );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$this->io = new SymfonyStyle( $input, $output );

		$this->logger = new Logger( 'mwcli' );
		$logLevel = Logger::ERROR;
		if ( $output->isVerbose() ) {
			$logLevel = Logger::WARNING;
		}
		if ( $output->isVeryVerbose() ) {
			$logLevel = Logger::INFO;
		}
		if ( $output->isDebug() ) {
			$logLevel = Logger::DEBUG;
		}
		$this->logger->pushHandler( new StreamHandler( fopen( 'php://stdout', 'w' ), $logLevel ) );
		$this->logger->debug( 'Logger configured at level ' . $logLevel );

		// 'wiki' is a common option for many commands, so we can validate it here.
		if ( $input->hasOption( 'wiki' ) && empty( $input->getOption( 'wiki' ) ) ) {
			$this->io->note( $this->msg( 'option-wiki-missing' ) );
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
	 * Get the default config directory (the root directory of mwcli).
	 * @return string The full filesystem path, always with a trailing slash.
	 */
	protected function getConfigDirDefault(): string {
		return rtrim( dirname( __DIR__, 2 ), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
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
		file_put_contents( $configPath, Yaml::dump( $config, 3 ) );
		$this->io->success( $this->msg( 'saved-config', [ $configPath ] ) );
	}

	protected function getApi( array $siteInfo ): MediawikiApi {
		// API.
		if ( isset( $siteInfo['api_url'] ) ) {
			$api = MediawikiApi::newFromApiEndpoint( $siteInfo['api_url'] );
		} elseif ( isset( $siteInfo['main_page_url'] ) ) {
			$api = MediawikiApi::newFromPage( $siteInfo['main_page_url'] );
		} else {
			throw new Exception( '$siteInfo does not include a URL.' );
		}

		// Logger.
		$api->setLogger( $this->logger );

		return $api;
	}

	/**
	 * @param InputInterface $input
	 * @return string[]|bool Site info, or false if none found.
	 */
	public function getSite( InputInterface $input ) {
		$config = $this->getConfig( $input );
		$wiki = $input->getOption( 'wiki' );
		$site = false;
		if ( isset( $config['sites'] ) ) {
			foreach ( $config['sites'] as $siteId => $s ) {
				if ( $siteId === $wiki ) {
					$site = $s;
					// Add ID now, so it can be used as the config.yml key and not duplicated in the site array there.
					$site[ 'id' ] = $siteId;
				}
			}
		}
		if ( !$site ) {
			$configPath = $input->getOption( 'config' );
			$this->io->warning( $this->msg( 'sites-auth-sitenotfound', [ $wiki, $configPath ] ) );
		}
		return $site;
	}

	/**
	 * @param InputInterface $input
	 * @param string $siteName
	 * @param array $site
	 */
	public function setSite( InputInterface $input, string $siteName, array $site ) {
		$config = $this->getConfig( $input );
		if ( !isset( $config['sites'] ) ) {
			$config['sites'] = [];
		}
		$config['sites'][$siteName] = $site;
		$this->saveConfig( $input, $config );
	}

	/**
	 * Log the user in to the given site. If the site doesn't have requisite authentication details, ask for them.
	 */
	public function login( InputInterface $input, MediawikiApi $api ) {
		$siteName = $input->getOption( 'wiki' );
		$site = $this->getSite( $input );
		if ( !isset( $site['username'] ) ) {
			$site['username'] = $this->io->ask( $this->msg( 'ask-login-username' ) );
			$this->setSite( $input, $siteName, $site );
		}
		if ( !isset( $site['password'] ) ) {
			$site['password'] = $this->io->askHidden( $this->msg( 'ask-login-password' ) );
			$this->setSite( $input, $siteName, $site );
		}

		// Try to log in.
		$site = $this->getSite( $input );
		return $api->login( new ApiUser( $site['username'], $site['password'] ) );
	}
}
