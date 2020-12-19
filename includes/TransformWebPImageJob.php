<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP;

use Exception;
use Job;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use Title;

/**
 * Creates webp images through the JobQueue
 */
class TransformWebPImageJob extends Job {
	protected $removeDuplicates = true;

	public function __construct( ?Title $title, array $params ) {
		parent::__construct( 'TransformWebPImage', $title, $params );
	}

	public function run(): bool {
		if ( !is_array( $this->params ) ) {
			$this->setLastError( 'Extension:WebP: Params is not an array.' );

			return false;
		}

		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->params['title'] );

		if ( !$file || !$file->exists() ) {
			$this->setLastError( sprintf( 'Extension:WebP: File "%s" does not exist', $this->params['title'] ) );

			return false;
		}

		try {
			$transformer = new WebPTransformer( $file, [ 'overwrite' => isset( $this->params['overwrite'] ) ] );
		} catch ( RuntimeException $e ) {
			$this->setLastError( $e->getMessage() );
			return false;
		}

		try {
			if ( isset( $this->params['width'] ) ) {
				$fakeThumb = new FakeMediaTransformOutput( (int)$this->params['width'], (int)$this->params['height'] );
				$status = $transformer->transformLikeThumb( $fakeThumb );
			} else {
                $status = $transformer->transform();
			}
		} catch ( Exception $e ) {
			$this->setLastError( $e->getMessage() );

			return false;
		}

        if (!$status->isOK()) {
            $this->setLastError($status->getMessage());

            return false;
        }

		return true;
	}
}
