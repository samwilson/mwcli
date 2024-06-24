<?php

namespace Samwilson\MediaWikiCLI\Command;

use Addwiki\Mediawiki\Api\Client\Action\Exception\UsageException;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Service\FileUploader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UploadFilesCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'upload:files' );
		$this->setDescription( $this->msg( 'command-upload-files-desc' ) );
		$this->addOption( 'wiki', 'w', InputOption::VALUE_REQUIRED, $this->msg( 'option-wiki-desc' ) );
		$this->addOption( 'comment', 'm', InputOption::VALUE_REQUIRED, $this->msg( 'option-comment-desc' ), '' );
		$this->addArgument( 'files', InputArgument::IS_ARRAY, $this->msg( 'arg-files-desc' ) );
	}

	public function execute( InputInterface $input, OutputInterface $output ) {
		$ret = parent::execute( $input, $output );
		if ( $ret ) {
			return $ret;
		}
		$files = $input->getArgument( 'files' );
		if ( empty( $files ) ) {
			$this->io->error( $this->msg( 'files-missing' ) );
			return Command::FAILURE;
		}
		$site = $this->getSite( $input );
		if ( !$site ) {
			return Command::FAILURE;
		}

		$api = $this->getApi( $site, $this->getAuthMethod( $input ) );
		$uploader = new FileUploader( $api );

		sort( $files );
		foreach ( $files as $file ) {
			$filePath = realpath( $file );
			if ( !is_file( $filePath ) ) {
				$this->io->warning( $this->msg( 'not-a-file', [ $filePath ] ) );
				continue;
			}
			$fileTitle = basename( $file );
			// See if the same file exists.
			$sha1 = sha1_file( $filePath );
			$fileExistsRequest = ActionRequest::factory()
				->setMethod( 'GET' )
				->setAction( 'query' )
				->setParam( 'list', 'allimages' )
				->setParam( 'aisha1', $sha1 );
			$fileExists = $api->request( $fileExistsRequest );
			if ( isset( $fileExists['query']['allimages'][0]['descriptionurl'] ) ) {
				$this->io->warning( $this->msg( 'file-exists', [ $fileExists['query']['allimages'][0]['descriptionurl'] ] ) );
				continue;
			}
			// See if the desired filename exists.
			$filenameExistsRequest = ActionRequest::factory()
				->setMethod( 'GET' )
				->setAction( 'query' )
				->setParam( 'titles', 'File:' . $fileTitle )
				->setParam( 'prop', 'info' )
				->setParam( 'inprop', 'url' );
			$filenameExists = $api->request( $filenameExistsRequest );
			$filenameExistsInfo = array_shift( $filenameExists['query']['pages'] );
			if ( isset( $filenameExistsInfo['pageid'] ) ) {
				$this->io->warning( $this->msg( 'filename-exists', [ $filenameExistsInfo['canonicalurl'] ] ) );
				continue;
			}
			// Upload.
			$pageText = '';
			$comment = $input->getOption( 'comment' );
			try {
				$uploaded = $uploader->upload( $fileTitle, $file, $pageText, $comment );
			} catch ( UsageException $e ) {
				$this->io->error( 'Unable to upload ' . $filePath );
				throw $e;
			}
			if ( $uploaded ) {
				$this->io->success( $this->msg( 'file-uploaded-successfully', [ $filenameExistsInfo['canonicalurl'] ] ) );
			}
		}
		return Command::SUCCESS;
	}
}
