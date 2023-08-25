<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Transformer;

use File;
use Status;

/**
 * This is only used for tests
 */
class NullTransformer extends AbstractBaseTransformer implements MediaTransformer {

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
	public static function changeExtension( string $path ): string {
		return sprintf(
			'%s.%s',
			trim( substr( $path, 0, -( strlen( pathinfo( $path, PATHINFO_EXTENSION ) ) ) ), '.' ),
			self::getFileExtension()
		);
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
}
