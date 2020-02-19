<?php

namespace Samwilson\MediaWikiCLI\Command;

use Mediawiki\Api\FluentRequest;
use Mediawiki\Api\MediawikiApi;
use PharData;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ExtensionInstallCommand extends CommandBase {

	/** @var string The extensions directory of MediaWiki. */
	protected $extensionsDir;

	public function configure() {
		parent::configure();
		$this->setName( 'extension:install' );
		$this->setDescription( $this->msg( 'command-extension-install-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addArgument(
			'extension-name',
			InputArgument::REQUIRED,
			$this->msg( 'argument-extension-name-desc' )
		);
		$this->addOption(
			'git',
			'g',
			InputOption::VALUE_NONE,
			$this->msg( 'option-git-desc' )
		);
		$this->addOption(
			'gituser',
			'u',
			InputOption::VALUE_REQUIRED,
			$this->msg( 'option-gituser-desc' )
		);
	}

	/**
	 * @param InputInterface $input An InputInterface instance
	 * @param OutputInterface $output An OutputInterface instance
	 *
	 * @return null|int null or 0 if everything went fine, or an error code
	 */
	public function execute( InputInterface $input, OutputInterface $output ) {
		parent::execute( $input, $output );

		// Get the site config and check required info.
		$site = $this->getSite( $input );
		if ( !$site ) {
			return 1;
		}
		if ( !isset( $site['install_path'] ) ) {
			$this->io->warning( $this->msg( 'no-install-path' ) );
			return 1;
		}
		$installPath = rtrim( $site['install_path'], '/' );
		if ( !is_dir( $installPath ) ) {
			$this->io->warning( $this->msg( 'install-path-not-found' ) );
			return 1;
		}

		$extensionName = ucfirst( $input->getArgument( 'extension-name' ) );
		$this->extensionsDir = $installPath . '/extensions';

		if ( is_dir( $this->extensionsDir . '/' . $extensionName ) ) {
			$this->io->success( $this->msg( 'extension-already-installed', [ $extensionName ] ) );
			return 0;
		}

		// Get site info.
		$siteApi = MediawikiApi::newFromApiEndpoint( $site['api_url'] );
		$siteinfoReq = FluentRequest::factory()->setAction( 'query' )
			// MediaWiki version.
			->setParam( 'meta', 'siteinfo' )
			->setParam( 'siprop', 'general|extensions' );
		$siteInfo = $siteApi->getRequest( $siteinfoReq );
		if ( !isset( $siteInfo['query']['extensions'] ) ) {
			$this->io->warning( $this->msg( 'extension-info-fetch-error' ) );
			return 1;
		}
		foreach ( $siteInfo['query']['extensions'] as $extension ) {
			if ( $extension['name'] === $extensionName ) {
				$this->io->success( $this->msg( 'extension-already-installed', [ $extensionName ] ) );
				return 0;
			}
		}

		// Get list of available extension versions.
		$api = MediawikiApi::newFromApiEndpoint( 'https://www.mediawiki.org/w/api.php' );
		$mediawikiOrgInfoReq = FluentRequest::factory()->setAction( 'query' )
			->setParam( 'list', 'extdistbranches' )
			->setParam( 'edbexts', $extensionName );
		$mediawikiOrgInfo = $api->getRequest( $mediawikiOrgInfoReq );
		if ( !isset( $mediawikiOrgInfo['query']['extdistbranches']['extensions'][ $extensionName ] ) ) {
			$this->io->warning( $this->msg( 'extension-not-found', [ $extensionName ] ) );
			return 1;
		}
		$branches = $mediawikiOrgInfo['query']['extdistbranches']['extensions'][ $extensionName ];
		preg_match( '/MediaWiki (\d+)\.(\d+)/', $siteInfo['query']['general']['generator'], $matches );
		$possibleBranch = "REL{$matches[1]}_{$matches[2]}";
		$branch = $branches[$possibleBranch] ?? 'master';

		// Download via Git or ExtensionDistributor.
		return ( $input->getOption( 'git' ) || $input->getOption( 'gituser' ) )
			? $this->downloadFromGit( $extensionName, $branch, $input->getOption( 'gituser' ) )
			: $this->downloadFromExtensionDistributor( $extensionName, $branch, $branches );
	}

	protected function downloadFromExtensionDistributor( string $extensionName, $branch, $branches ) {
		$sourceurl = $branches[$branch];
		$destFile = "$this->extensionsDir/$extensionName-$branch.tgz";
		if ( file_exists( $destFile ) ) {
			$this->io->writeln( $this->msg( 'extension-already-downloaded', [ $destFile ] ) );
		} else {
			copy( $sourceurl, $destFile );
			$this->io->writeln( $this->msg( 'extension-downloaded', [ $destFile ] ) );
		}
		$destDir = $this->extensionsDir . '/' . $extensionName;
		$this->io->writeln( $this->msg( 'extracting-extension', [ $destDir ] ) );
		$phar = new PharData( $destFile );
		$phar->extractTo( $this->extensionsDir );
		$this->io->success( $this->msg( 'extension-installed', [ $extensionName, $destDir ] ) );
		return 0;
	}

	protected function downloadFromGit( $extensionName, $branch, $username = null ) {
		// Clone Git repository.
		$repoUrl = "https://gerrit.wikimedia.org/r/mediawiki/extensions/$extensionName";
		if ( $username ) {
			$repoUrl = "ssh://$username@gerrit.wikimedia.org:29418/mediawiki/extensions/$extensionName";
		}
		// Run the Git command.
		$destDir = $this->extensionsDir . '/' . $extensionName;
		$command = [ 'git', 'clone', '--origin', 'gerrit', $repoUrl, '--branch', $branch, $destDir ];
		$process = new Process( $command );
		$process->mustRun();
		$this->io->success( $this->msg( 'extension-cloned', [ $extensionName, $destDir ] ) );
		return 0;
	}
}
