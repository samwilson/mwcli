<?php

namespace Samwilson\MediaWikiCLI\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReadmeGenCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'readme' );
		$this->setHidden( true );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$excludedCommands = [ 'help', 'list', 'completion', $this->getName() ];
		$commandInfo = '';
		foreach ( $this->getApplication()->all() as $command ) {
			if ( in_array( $command->getName(), $excludedCommands ) || $command->isHidden() ) {
				continue;
			}
			$commandInfo .= "\n### " . $command->getName() . "\n\n"
				. $command->getDescription() . "\n\n"
				. '    ' . $command->getSynopsis() . "\n\n";
			$options = $command->getDefinition()->getOptions();
			foreach ( $options as $option ) {
				$shortcut = $option->getShortcut() ? ' `-' . $option->getShortcut() . '`' : '';
				$required = !$option->getDefault() && $option->isValueRequired() ? '*Required.*' : '';
				$default = $option->getDefault() ? 'Default: ' . var_export( $option->getDefault(), true ) : '';
				$commandInfo .= '* `--' . $option->getName() . "`$shortcut"
					. ' — ' . $option->getDescription() . "\n"
					. ( $default . $required ? '  ' . $default . $required . "\n" : '' );
			}
			foreach ( $command->getDefinition()->getArguments() as $argument ) {
				$commandInfo .= '* `<' . $argument->getName() . '>` ' . $argument->getDescription() . "\n";
			}
		}

		// Remove local paths.
		$commandInfo = str_replace( getcwd(), '[CWD]', $commandInfo );

		// Write new contents to README.md.
		$readmePath = dirname( __DIR__, 2 ) . '/README.md';
		$readme = file_get_contents( $readmePath );
		preg_match( "/(.*## Usage\n)(.*)(\n## License: MIT.*)/s", $readme, $matches );
		$newReadme = $matches[1] . $commandInfo . $matches[3];
		if ( $newReadme !== $readme ) {
			file_put_contents( $readmePath, $newReadme );
			$output->writeln( 'Updated README.md' );
		}

		return 0;
	}

}
