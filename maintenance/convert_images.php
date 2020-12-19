<?php

declare( strict_types=1 );

use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ConvertImages extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Creates WebP versions of each uploaded File.' );
		$this->addOption( 'no-thumbs', 'Disable WebP creation of thumbnails.' );
		$this->addOption( 'thumb-sizes', 'Sizes of thumbs to generate. Provide a comma separated list of sizes like 1000,1200.' );
		$this->addOption( 'titles', 'Work on these images instead of all. Provide a comma separated list of titles like Title1.jpg,Title2.jpg.' );
		$this->addOption( 'overwrite', 'Overwrite files if they already exist.' );
		$this->setBatchSize( 100 );

		$this->requireExtension( 'WebP' );
	}

	public function execute() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getMaintenanceConnectionRef( DB_REPLICA );

		if ( $this->hasOption( 'titles' ) ) {
			$result = explode( ',', $this->getOption( 'titles' ) );
			$result = array_map( static function ( $entry ) {
				return (object)[ 'page_title' => trim( $entry ) ];
			}, $result );
		} else {
			$result = $dbr->select(
				[ 'page' ],
				[ 'page_title' ],
				[ 'page_namespace' => NS_FILE ],
				__METHOD__
			);

			if ( !$result->valid() ) {
				$this->error( 'Could not get Images.' );
			}
		}

		$jobs = [];

		foreach ( $result as $item ) {
			$jobs[] = new TransformWebPImageJob(
				Title::newFromText( $item->page_title, NS_FILE ),
				[
					'title' => $item->page_title,
					'overwrite' => $this->hasOption( 'overwrite' ),
				]
			);

			if ( !$this->hasOption( 'no-thumbs' ) ) {
				$jobs = array_merge( $jobs, $this->makeThumbnailJobs( $item->page_title ) );
			}
		}

		JobQueueGroup::singleton()->push( $jobs );
	}

	private function makeThumbnailJobs( string $title ): array {
		$sizes = MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPThumbSizes' );

		if ( $this->hasOption( 'thumb-sizes' ) ) {
			$sizes = explode( ',', $this->getOption( 'thumb-sizes', '' ) );
		}

		$jobs = [];

		foreach ( $sizes as $size ) {
			$jobs[] = new TransformWebPImageJob(
				Title::newMainPage(),
				[
					'title' => $title,
					'width' => $size,
					'height' => 0, // Auto size,
					'overwrite' => $this->hasOption( 'overwrite' ),
				]
			);
		}

		return $jobs;
	}
}

$maintClass = ConvertImages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
