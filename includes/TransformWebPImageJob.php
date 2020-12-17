<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP;

use Job;
use MediaWiki\MediaWikiServices;

/**
 * This is currently not working
 */
class TransformWebPImageJob extends Job {

	protected $removeDuplicates = true;

	public function run(): bool {
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->getParams()['title'] );

		if ( !$file->exists() ) {
			wfLogWarning( 'Extension:WebP: File does not exist' );

			return false;
		}

		$transformer = new WebPTransformer( $file );

		if ( isset( $this->getParams()['likeThumb'] ) ) {
			$fakeThumb = new FakeMediaTransformOutput( $this->getParams()['width'], $this->getParams()['height'] );
			$transformer->transformLikeThumb( $fakeThumb );
		} else {
			$transformer->transform();
		}

		return true;
	}
}
