<?php

namespace Samwilson\MediaWikiCLI\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class SitesCreateCommandTest extends TestCase {

	/**
	 * @covers \Samwilson\MediaWikiCLI\Command\SitesCreateCommand
	 */
	public function testCreateSite(): void {
		$installDir = dirname( __DIR__ ) . '/temp/tests/test-create';
		$fs = new Filesystem();
		$fs->mkdir( dirname( $installDir ), 0755 );

		// Use a custom config file, so we can inspect its contents after.
		$configFile = dirname( $installDir ) . '/test-create_config.php';
		$process = new Process( [
			'bin/mwcli', 'sites:create',
			'--installdir', $installDir,
			'--config', $configFile,
			'--wiki', 'testwiki',
			'--dbname', $_ENV['MWCLI_DBNAME'],
			'--dbpass', $_ENV['MWCLI_DBPASS'],
			'--dbuser', $_ENV['MWCLI_DBUSER'],
			'--dbtype', 'sqlite',
			'--adminpass', 'test123test',
		] );
		$process->mustRun();
		static::assertFileExists( $installDir . '/LocalSettings.php' );
		static::assertFileExists( $configFile );

		// Clean up.
		$fs->remove( [ $installDir, $configFile ] );
	}
}
