<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Transformer;

use File;
use InvalidArgumentException;

class TransformerFactory {

	/**
	 * @param string $className
	 * @param array $args
	 * @return MediaTransformer
	 * @throws InvalidArgumentException
	 */
	public function getInstance( string $className, array $args ) {
		if ( empty( $args ) || !$args[0] instanceof File ) {
			throw new InvalidArgumentException( 'First entry of $args must be a File' );
		}

		switch ( $className ) {
			case WebPTransformer::class:
			case 'webp':
				return new WebPTransformer( ...$args );

			case AvifTransformer::class:
			case 'avif':
				return new AvifTransformer( ...$args );

			default:
				throw new InvalidArgumentException( sprintf(
					'Transformer "%s" not recognized', $className
				) );
		}
	}
}
