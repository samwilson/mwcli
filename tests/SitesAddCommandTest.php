<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SitesAddCommandTest extends TestCase {

	/**
	 * @covers \Samwilson\MediaWikiCLI\Command\SitesAddCommand
	 */
	public function testAddSite(): void {
		$configFile = __DIR__ . '/config.yml';
		$process = new Process( [ 'bin/mwcli', 'sites:add', '--config', $configFile, '--url', 'https://en.wikipedia.org/' ] );
		$process->mustRun();
		static::assertFileExists( $configFile );
		static::assertEquals(
			"sites:\n    enwiki: { name: Wikipedia, main_page_url: 'https://en.wikipedia.org/wiki/Main_Page', api_url: 'https://en.wikipedia.org/w/api.php' }\n",
			file_get_contents( $configFile )
		);
		unlink( $configFile );
	}
}
