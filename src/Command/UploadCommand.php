<?php

namespace Samwilson\MediaWikiCLI\Command;

use Mediawiki\Api\FluentRequest;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\Service\FileUploader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UploadCommand extends CommandBase {

	public function configure() {
		parent::configure();
		$this->setName( 'upload' );
		$this->setDescription( $this->msg( 'command-upload-desc' ) );
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
			return 1;
		}
		$site = $this->getSite( $input );
		if ( !$site ) {
			return 1;
		}

		$api = MediawikiApi::newFromApiEndpoint( $site['api_url'] );
		$uploader = new FileUploader( $api );

		$this->login( $input, $api );

		foreach ( $files as $file ) {
			$filePath = realpath( $file );
			if ( !is_file( $filePath ) ) {
				$this->io->warning( $this->msg( 'not-a-file', [ $filePath ] ) );
				continue;
			}
			$fileTitle = basename( $file );
			// See if the same file exists.
			$sha1 = sha1_file( $filePath );
			$fileExistsRequest = FluentRequest::factory()
				->setAction( 'query' )
				->setParam( 'list', 'allimages' )
				->setParam( 'aisha1', $sha1 );
			$fileExists = $api->getRequest( $fileExistsRequest );
			if ( isset( $fileExists['query']['allimages'][0]['descriptionurl'] ) ) {
				$this->io->warning( $this->msg( 'file-exists', [ $fileExists['query']['allimages'][0]['descriptionurl'] ] ) );
				continue;
			}
			// See if the desired filename exists.
			$filenameExistsRequest = FluentRequest::factory()
				->setAction( 'query' )
				->setParam( 'titles', 'File:' . $fileTitle )
				->setParam( 'prop', 'info' )
				->setParam( 'inprop', 'url' );
			$filenameExists = $api->getRequest( $filenameExistsRequest );
			$filenameExistsInfo = array_shift( $filenameExists['query']['pages'] );
			if ( isset( $filenameExistsInfo['pageid'] ) ) {
				$this->io->warning( $this->msg( 'filename-exists', [ $filenameExistsInfo['canonicalurl'] ] ) );
				continue;
			}
			// Upload.
			$pageText = '';
			$comment = $input->getOption( 'comment' );
			$uploaded = $uploader->upload( $fileTitle, $file, $pageText, $comment );
			if ( $uploaded ) {
				$this->io->success( $this->msg( 'file-uploaded-successfully', [ $filenameExistsInfo['canonicalurl'] ] ) );
			}
		}
	}
}
