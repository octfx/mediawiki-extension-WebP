<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP;

use File;
use MediaTransformError;

class WebPMediaHandler extends \WebPHandler {

	/**
	 * @param File $image
	 * @param array $params
	 * @return false|MediaTransformError
	 */
	protected function transformImageMagick( $image, $params ) {
		$dimensions = new FakeMediaTransformOutput(
			$params['physicalWidth'] ?? $image->getWidth(),
			$params['physicalHeight'] ?? $image->getHeight()
		);

		$transformer = new WebPTransformer( $image );

		return !$transformer->transformLikeThumb( $dimensions )->isOK();
	}

	/**
	 * @param File $image
	 * @param array $params
	 * @return false|MediaTransformError
	 */
	protected function transformImageMagickExt( $image, $params ) {
		return $this->transformImageMagick( $image, $params );
	}

	public function getThumbType( $ext, $mime, $params = null ) {
		return [ 'webp', 'image/webp' ];
	}
}
