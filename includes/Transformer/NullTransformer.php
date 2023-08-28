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
use Status;

/**
 * This is only used for tests
 */
class NullTransformer extends AbstractBaseTransformer {

	/**
	 * @var Status
	 */
	public static $transformStatus;

	/**
	 * @var bool|callable
	 */
	public static $canTransform = true;

	/**
	 * @var string
	 */
	public static string $fileExtension = 'foo';

	/**
	 * @var string
	 */
	public static string $mimeType = 'image/foo';

	/**
	 * @inheritDoc
	 */
	public function __construct( File $file, array $options = [] ) {
	}

	/**
	 * @inheritDoc
	 */
	public static function checkExtensionsLoaded(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function transformLikeThumb( int $width ): Status {
		return self::$transformStatus;
	}

	/**
	 * @inheritDoc
	 */
	public function transform(): Status {
		return self::$transformStatus;
	}

	/**
	 * @inheritDoc
	 */
	public static function canTransform( File $file ): bool {
		if ( is_callable( self::$canTransform ) ) {
			return call_user_func( self::$canTransform, $file );
		}

		return self::$canTransform;
	}

	/**
	 * @inheritDoc
	 */
	public static function getFileExtension(): string {
		return self::$fileExtension;
	}

	/**
	 * @inheritDoc
	 */
	public static function getMimeType(): string {
		return self::$mimeType;
	}

    /**
     * @inheritDoc
     */
	public function transformImage( $outPath, int $width = -1 ): bool {
		return true;
	}
}
