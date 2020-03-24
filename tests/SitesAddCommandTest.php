<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SitesAddCommandTest extends TestCase {

	/**
	 * @covers \Samwilson\MediaWikiCLI\Command\SitesAddCommand
	 * @dataProvider provideAddSite
	 */
	public function testAddSite( $configFilename ): void {
		$configFile = __DIR__ . '/' . $configFilename;
		$process = new Process( [ 'bin/mwcli', 'sites:add', '--config', $configFile, '--url', 'https://en.wikipedia.org/' ] );
		$process->mustRun();
		static::assertFileExists( $configFile );
		static::assertEquals(
			"sites:\n    enwiki:\n"
			. "        name: Wikipedia\n"
			. "        main_page_url: 'https://en.wikipedia.org/wiki/Main_Page'\n"
			. "        api_url: 'https://en.wikipedia.org/w/api.php'\n",
			file_get_contents( $configFile )
		);
		unlink( $configFile );
	}

	public function provideAddSite() {
		return [
			[ 'config.yml' ],
			[ 'foobar.yaml' ]
		];
	}
}
