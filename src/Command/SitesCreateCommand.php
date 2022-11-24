<?php

namespace Samwilson\MediaWikiCLI\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use XdgBaseDir\Xdg;
use ZipArchive;

class SitesCreateCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'sites:create' );
		$this->setDescription( $this->msg( 'command-sites-create-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addOption( 'mwver', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-mwver-desc' ) );
		$this->addOption( 'installdir', 'i', InputOption::VALUE_REQUIRED, $this->msg( 'option-installdir-desc' ), getcwd() );
		$this->addOption( 'dbname', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-dbname-desc' ) );
		$this->addOption( 'dbserver', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-dbserver-desc' ) );
		$this->addOption( 'dbport', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-dbport-desc' ) );
		$this->addOption( 'dbtype', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-dbtype-desc' ) );
		$this->addOption( 'dbuser', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-dbuser-desc' ) );
		$this->addOption( 'dbpass', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-dbpass-desc' ) );
		$this->addOption( 'wikiserver', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-server-desc' ) );
		$this->addOption( 'adminuser', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-adminuser-desc' ), 'Admin' );
		$this->addOption( 'adminpass', null, InputOption::VALUE_REQUIRED, $this->msg( 'option-adminpass-desc' ) );
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

		$config = $this->getConfig( $input );
		$siteId = $input->getOption( 'wiki' );
		if ( isset( $config['sites'][ $siteId ] ) ) {
			$this->io->error( $this->msg( 'sites-create-site-already-exists', [ $siteId ] ) );
			return Command::FAILURE;
		}

		$installOptionsRequired = [
			'dbname',
			'dbuser',
			'dbpass',
			'wiki',
			'adminpass',
		];
		foreach ( $installOptionsRequired as $installOpt ) {
			if ( empty( $input->getOption( $installOpt ) ) ) {
				$this->io->error( $this->msg( 'install-option-required', [ $installOpt ] ) );
				return Command::FAILURE;
			}
		}

		$installDir = rtrim( Path::makeAbsolute( $input->getOption( 'installdir' ), getcwd() ), PATH_SEPARATOR );

		// Make sure the install directory is empty or absent.
		$installDirExists = file_exists( $installDir );
		$installDirEmpty = empty( glob( $installDir . '/*' ) );
		if ( $installDirExists && !$installDirEmpty ) {
			$this->io->error( $this->msg( 'sites-create-directory-not-empty', [ $installDir ] ) );
			return 1;
		}

		$xdg = new Xdg();
		$cacheDir = $xdg->getHomeCacheDir() . '/mwcli';
		$filesystem = new Filesystem();

		// Download latest version to a temp directory.
		// @todo Lookup actual latest version.
		$versionPatch = '1.40.0';
		$versionMinor = '1.40';
		$releaseUrl = "https://releases.wikimedia.org/mediawiki/$versionMinor/mediawiki-$versionPatch.zip";
		if ( !is_dir( $cacheDir ) ) {
			$this->io->writeln( 'Creating directory: ' . $cacheDir );
			$filesystem->mkdir( $cacheDir, 0755 );
		}
		$installTarball = $cacheDir . '/mediawiki-' . $versionPatch . '.zip';
		// @todo Check file integrity.
		if ( !file_exists( $installTarball ) ) {
			$this->io->write( 'Downloading MediaWiki to ' . $installTarball . ' . . . ' );
			$client = new Client();
			$downloadResult = $client->request( 'GET', $releaseUrl, [ 'sink' => $installTarball ] );
			if ( $downloadResult->getStatusCode() !== 200 ) {
				$this->io->error( $downloadResult->getReasonPhrase() );
				return null;
			}
			$this->io->writeln( 'done.' );
		}

		// Extract.
		$extractDir = $cacheDir . '/mediawiki-' . $versionPatch;
		if ( !is_dir( $extractDir ) || empty( glob( $extractDir . '/*' ) ) ) {
			$this->io->writeln( 'Extracting to ' . $extractDir );
			$zip = new ZipArchive();
			$zip->open( $installTarball );
			$zip->extractTo( $extractDir );
			$zip->close();
		}

		// Move to final destination.
		$extractDirTopDir = "$extractDir/mediawiki-$versionPatch/";
		$this->io->writeln( 'Moving from ' . $extractDirTopDir . ' to ' . $installDir );
		$filesystem->rename( $extractDirTopDir, $installDir );
		$filesystem->remove( $extractDir );

		// Run the install and update commands.
		$this->runInstallCommand( $installDir, $input );
		$this->runUpdateCommand( $installDir );

		// Add to the config file.
		$this->setSite( $input, $siteId, [
			'name' => $siteId,
			'install_path' => $installDir,
			'main_page_url' => '',
			'api_url' => '',
		] );

		$this->io->success( $this->msg( 'mediawiki-installed', [ $installDir ] ) );
		return Command::SUCCESS;
	}

	private function runInstallCommand( string $installDir, InputInterface $input ) {
		// Compile install command.
		$installOptions = [
			'dbname' => 'dbname',
			'dbport' => 'dbport',
			'dbserver' => 'dbserver',
			'dbtype' => 'dbtype',
			'dbuser' => 'dbuser',
			'dbpass' => 'dbpass',
			'wiki' => 'wiki',
			'adminuser' => false,
			'adminpass' => 'pass',
		];
		$cmdParams = [ 'php', "$installDir/maintenance/run", 'install' ];
		foreach ( $installOptions as $mwcliOptName => $installPhpOptName ) {
			if ( $input->getOption( $mwcliOptName ) && $installPhpOptName ) {
				$cmdParams[] = '--' . $installPhpOptName;
				$cmdParams[] = $input->getOption( $mwcliOptName );
			}
		}
		// Set the initial wiki site title to the same as the shortname, to require fewer CLI options.
		$cmdParams[] = $input->getOption( 'wiki' );
		$cmdParams[] = $input->getOption( 'adminuser' );
		$cmd = new Process( $cmdParams );
		$cmd->setWorkingDirectory( $installDir );
		$cmd->mustRun();
	}

	private function runUpdateCommand( string $installDir ) {
		$cmdParams = [ 'php', "$installDir/maintenance/run", 'update', '--quick' ];
		$cmd = new Process( $cmdParams );
		$cmd->setWorkingDirectory( $installDir );
		$cmd->mustRun();
	}
}
