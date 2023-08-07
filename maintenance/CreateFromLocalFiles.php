<?php

declare( strict_types=1 );

use MediaWiki\Extension\WebP\TransformImageJob;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class CreateFromLocalFiles extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Creates transformed versions for each uploaded File.' );
		$this->addOption( 'no-thumbs', 'Disable creation of thumbnails.' );
		$this->addOption( 'only-thumbs', 'Only create thumbnails.' );
		$this->addOption( 'thumb-sizes', 'Sizes of thumbs to generate. Provide a comma separated list of sizes like 1000,1200.' );
		$this->addOption( 'titles', 'Work on these images instead of all. Provide a comma separated list of titles like Title1.jpg,Title2.jpg.' );
		$this->addOption( 'overwrite', 'Overwrite files if they already exist.' );
		$this->addOption( 'title-prefix', 'Page title prefix.' );
		$this->addOption( 'file-type', 'File type to work on. Write file extension without dot.' );
		$this->setBatchSize( 100 );

		$this->requireExtension( 'WebP' );
	}

	public function execute() {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getMaintenanceConnectionRef( DB_REPLICA );
		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( $this->hasOption( 'titles' ) ) {
			$result = explode( ',', $this->getOption( 'titles' ) );
			$result = array_map( static function ( $entry ) {
				$entry = str_replace( ' ', '_', $entry );

				return (object)[ 'page_title' => trim( $entry ) ];
			}, $result );
		} else {
			$conditions = [
				'page_namespace' => NS_FILE,
			];

			if ( $this->hasOption( 'title-prefix' ) ) {
				$conditions[] = sprintf( 'page_title LIKE \'%s%%\'', $this->getOption( 'title-prefix' ) );
			}

			if ( $this->hasOption( 'file-type' ) ) {
				$conditions[] = sprintf( 'page_title LIKE \'%%%s\'', $this->getOption( 'file-type' ) );
			}

			$result = $dbr->select(
				[ 'page' ],
				[ 'page_title' ],
				$conditions,
				__METHOD__
			);

			if ( !$result->valid() ) {
				if ( $result->numRows() === 0 ) {
					$this->output( 'Query does not match any pages.' );
				} else {
					$this->error( 'Could not get Images.' );
				}
			}
		}

		$jobs = [];

		foreach ( $result as $item ) {
			if ( preg_match( '/(jpe?g|png)/i', $item->page_title ) !== 1 ) {
				continue;
			}

			foreach ( $config->get( 'EnabledTransformers' ) as $transformer ) {
				if ( !$this->hasOption( 'only-thumbs' ) ) {
					$jobs[] = new TransformImageJob(
						null,
						[
							'title' => $item->page_title,
							'overwrite' => $this->hasOption( 'overwrite' ),
							'transformer' => $transformer,
						]
					);
				}
			}

			if ( !$this->hasOption( 'no-thumbs' ) ) {
				$jobs = array_merge( $jobs, $this->makeThumbnailJobs( $item->page_title ) );
			}
		}

		$group = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup();

		$group->push( $jobs );
	}

	private function makeThumbnailJobs( string $title ): array {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		try {
			$sizes = $config->get( 'WebPThumbSizes' );
		} catch ( ConfigException $e ) {
			$sizes = [];
		}

		if ( $this->hasOption( 'thumb-sizes' ) ) {
			$sizes = explode( ',', $this->getOption( 'thumb-sizes', '' ) );
		}

		$jobs = [];

		foreach ( $sizes as $size ) {
			foreach ( $config->get( 'EnabledTransformers' ) as $transformer ) {
				$jobs[] = new TransformImageJob(
					null,
					[
						'title' => $title,
						'width' => $size,
						'overwrite' => $this->hasOption( 'overwrite' ),
						'transformer' => $transformer,
					]
				);
			}
		}

		return $jobs;
	}
}

$maintClass = CreateFromLocalFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
