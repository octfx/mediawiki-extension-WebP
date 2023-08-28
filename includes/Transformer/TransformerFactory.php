<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

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
