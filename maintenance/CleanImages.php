<?php

declare( strict_types=1 );
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class CleanImages extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Removes generated WebP files' );
		$this->addOption( 'thumbs', 'Remove all generated webp thumbs' );
		$this->addOption( 'images', 'Remove all generated webp images' );
		$this->addOption( 'force', 'Do the actual deletion.' );
		$this->setBatchSize( 100 );

		$this->requireExtension( 'WebP' );
	}

	public function execute() {
		$images = 'mwstore://local-backend/local-public/webp';
		$thumbs = 'mwstore://local-backend/local-public/thumb/webp';
		$repo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();

		if ( $this->getOption( 'thumbs' ) !== null ) {
			$this->output( "Removing thumbnails\n" );
			$delete = $this->getOption( 'force' ) !== null;

			$files = $repo->getBackend()->getFileList( [
				'dir' => $thumbs
			] );

			$this->delete( $files, $thumbs, $delete );

			$repo->quickCleanDir( $thumbs );
		}

		if ( $this->getOption( 'images' ) !== null ) {
			$this->output( "Removing images\n" );
			$delete = $this->getOption( 'force' ) !== null;

			$files = $repo->getBackend()->getFileList( [
				'dir' => $images
			] );

			$this->delete( $files, $images, $delete );

			$repo->quickCleanDir( $images );
		}
	}

	private function delete( $files, $backend, bool $delete = false ): void {
		$repo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();

		foreach ( $files as $thumb ) {
			if ( !$delete ) {
				$this->output( sprintf( "[DRY RUN] Deleting %s\n", $thumb ) );
			} else {
				$this->output( sprintf( "Deleting %s\n", $thumb ) );
				$repo->quickPurge( sprintf( '%s/%s', $backend, $thumb ) );

				$dir = explode( '/', $thumb );
				array_pop( $dir );
				$repo->quickCleanDir( sprintf( '%s/%s', $backend, implode( '/', $dir ) ) );

				array_pop( $dir );
				$repo->quickCleanDir( sprintf( '%s/%s', $backend, implode( '/', $dir ) ) );
			}
		}
	}
}

$maintClass = CleanImages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
