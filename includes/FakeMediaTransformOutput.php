<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP;

use MediaTransformOutput;

/**
 * A wrapper for MediaTransformOutput
 * Currently used in Job Class
 */
class FakeMediaTransformOutput extends MediaTransformOutput {

	public function __construct( int $width, int $height ) {
		$this->width = $width;
		$this->height = $height;
	}

	/**
	 * @inheritDoc
	 */
	public function toHtml( $options = [] ) {
		return '';
	}
}
