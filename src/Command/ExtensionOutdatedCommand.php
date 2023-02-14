<?php

namespace Samwilson\MediaWikiCLI\Command;

use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Client\MediaWiki;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XdgBaseDir\Xdg;

class ExtensionOutdatedCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'extension:outdated' );
		$this->setDescription( $this->msg( 'command-extension-outdated-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
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
			return Command::FAILURE;
		}

		$extsAll = $this->getAvailableExtensions();
		if ( !$extsAll ) {
			return Command::FAILURE;
		}

		// Get info about installed extensions.
		$siteinfoReq = ActionRequest::simpleGet( 'query', [ 'meta' => 'siteinfo', 'siprop' => 'extensions' ] );
		$siteInfo = MediaWiki::newFromEndpoint( $site['api_url'] )
			->action()
			->request( $siteinfoReq );
		$installedExtensions = $siteInfo['query']['extensions'] ?? null;

		// Put it all together and output.
		$out = [];
		foreach ( $installedExtensions as $installedExtension ) {
			$extName = $installedExtension['name'];
			if ( !isset( $extsAll[$extName] ) ) {
				continue;
			}
			$verLatest = $extsAll[$extName]['version'] ?? null;
			$verInstalled = $installedExtension['version'] ?? null;
			$style = version_compare( $verLatest, $verInstalled, '>' ) ? 'error' : 'info';
			$out[$extName] = [
				$extName,
				$verInstalled,
				"<$style>$verLatest</$style>"
			];
		}
		ksort( $out );
		$this->io->table( [
			$this->msg( 'extension-outdated-header-name' ),
			$this->msg( 'extension-outdated-header-installed' ),
			$this->msg( 'extension-outdated-header-latest' ),
		], $out );
		return Command::SUCCESS;
	}

	private function getAvailableExtensions(): ?array {
		$extJsonUrl = 'https://extjsonuploader.toolforge.org/ExtensionJson.json';
		$client = new Client();
		$xdg = new Xdg();
		$extJsonFilename = $xdg->getHomeCacheDir() . '/mwcli/ExtensionJson.json';
		if ( !file_exists( $extJsonFilename ) || filemtime( $extJsonFilename ) < time() - 60 * 60 * 24 ) {
			$this->io->writeln( "Downloading $extJsonUrl" );
			$cacheDir = dirname( $extJsonFilename );
			if ( !is_dir( $cacheDir ) ) {
				$this->io->writeln( "Creating directory: $cacheDir" );
				mkdir( $cacheDir, 0777, true );
			}
			$result = $client->request( 'GET', $extJsonUrl, [ 'sink' => $extJsonFilename ] );
			if ( $result->getStatusCode() !== 200 ) {
				$this->io->error( $result->getReasonPhrase() );
				return null;
			}
		} else {
			$this->io->writeln( "Using cached extension data: $extJsonFilename" );
		}
		$extJson = file_get_contents( $extJsonFilename );
		$extData = json_decode( $extJson, true );
		if ( $extData === null ) {
			$this->io->error( "Unable to decode JSON in $extJsonFilename" );
			return null;
		}
		return $extData;
	}
}
