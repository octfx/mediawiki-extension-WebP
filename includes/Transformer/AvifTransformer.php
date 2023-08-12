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
use FileRepo;
use Imagick;
use ImagickException;
use ImagickPixel;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use Status;
use TempFSFile;

/**
 * Main class for transforming images into webp files
 */
class AvifTransformer extends AbstractBaseTransformer implements MediaTransformer {
	/**
	 * Supported files
	 *
	 * @var string[]
	 */
	public static $supportedMimes = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/webp',
		// 'image/gif',
	];

	/**
	 * @var array
	 */
	private $options;

	public function __construct( File $file, array $options = [] ) {
		if ( !self::canTransform( $file ) ) {
			throw new RuntimeException(
				sprintf(
					'Mimetype "%s" is not in supported mime: [%s]',
					$file->getMimeType(),
					implode( ', ', self::$supportedMimes )
				)
			);
		}

		$this->file = $file;
		$this->options = $options;
		$this->config = MediaWikiServices::getInstance()->getMainConfig();
	}

	/**
	 * Create a webp image based on thumbnail dimensions
	 *
	 * @param int $width
	 * @return Status
	 *
	 * @throws ImagickException
	 */
	public function transformLikeThumb( int $width ): Status {
		$tempFile = $this->getTempFile( self::getFileExtension() );

		$out = $this->file->getThumbRel(
			sprintf(
				'%dpx-%s',
				$width,
				self::changeExtension( $this->file->getName() )
			)
		);

		$out = sprintf( '%s/%s', self::getFileExtension(), $out );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Out path is: %s', 'AvifTransformer', __FUNCTION__, $out ) );

		if ( $this->checkFileExists( $out, 'thumb' ) && !$this->shouldOverwrite() ) {
			return Status::newGood();
		}

		$result = $this->transformImage( $tempFile, $width );

		if ( !$result ) {
			return Status::newFatal( 'Could not convert Image' );
		}

		$status = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->store(
			$tempFile,
			'thumb',
			$out,
			( $this->shouldOverwrite() ? FileRepo::OVERWRITE : 0 ) & FileRepo::SKIP_LOCKING
		);

		$this->logStatus( $status );

		return $status;
	}

	/**
	 * Transform the original file to a webp one
	 *
	 * @return Status
	 *
	 * @throws ImagickException
	 */
	public function transform(): Status {
		$tempFile = $this->getTempFile( self::getFileExtension() );

		$out = self::changeExtension( $this->file->getRel() );
		$out = sprintf( '%s/%s', self::getFileExtension(), $out );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Out path is: %s', 'AvifTransformer', __FUNCTION__, $out ) );

		if ( $this->checkFileExists( $out, 'public' ) && !$this->shouldOverwrite() ) {
			wfDebugLog( 'WebP', sprintf( '[%s::%s] File exists, skipping transform', 'AvifTransformer', __FUNCTION__ ) );

			return Status::newGood();
		}

		$result = $this->transformImage( $tempFile );

		if ( !$result ) {
			return Status::newFatal( 'Could not convert Image' );
		}

		$status = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->store(
			$tempFile,
			'public',
			$out,
			( $this->shouldOverwrite() ? FileRepo::OVERWRITE : 0 ) & FileRepo::SKIP_LOCKING
		);

		$this->logStatus( $status );

		return $status;
	}

	/**
	 * Change out a file extension to webp
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function changeExtension( string $path ): string {
		return sprintf( '%s.avif', trim( substr( $path, 0, -( strlen( pathinfo( $path, PATHINFO_EXTENSION ) ) ) ), '.' ) );
	}

	/**
	 * @param File $file
	 * @return bool
	 */
	public static function canTransform( File $file ): bool {
		return in_array( $file->getMimeType(), self::$supportedMimes ) && self::checkExtensionsLoaded();
	}

	/**
	 * Check if Imagick is installed
	 *
	 * @throws RuntimeException
	 */
	private static function checkExtensionsLoaded(): bool {
		return ( ( extension_loaded( 'imagick' ) && !empty( Imagick::queryformats( 'AVIF' ) ) ) ||
			( extension_loaded( 'gd' ) && ( gd_info()['AVIF Support'] ?? false ) === true ) );
	}

	/**
	 * Check if the overwrite flag was set
	 *
	 * @return bool
	 */
	private function shouldOverwrite(): bool {
		return isset( $this->options['overwrite'] );
	}

	/**
	 * @param TempFSFile|string $outPath
	 * @param int $width
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	private function transformImage( $outPath, int $width = -1 ): bool {
		if ( $outPath instanceof TempFSFile ) {
			$outPath = $outPath->getPath();
		}

		$result = $this->transformImagick( $outPath, $width );

		if ( !$result ) {
			$result = $this->transformGD( $outPath, $width );
		}

		return $result;
	}

	/**
	 * Prepare to transform the image
	 * Options are configurable
	 *
	 * @param string $outPath
	 * @param int $width
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	private function transformImagick( string $outPath, int $width = -1 ): bool {
		// Transparent backgrounds only work in imagick 7+
		if ( !extension_loaded( 'imagick' ) || ( Imagick::getVersion()['versionNumber'] ?? 0 ) <= 1691 ) {
			return false;
		}

		$image = new Imagick( $this->file->getLocalRefPath() );

		$image->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );

		$image = $image->mergeImageLayers( Imagick::LAYERMETHOD_MERGE );
		$image->setCompression( Imagick::COMPRESSION_BZIP );

		$image->setCompressionQuality( $this->getConfigValue( 'WebPCompressionQualityAvif' ) );
		$image->setImageCompressionQuality( $this->getConfigValue( 'WebPCompressionQualityAvif' ) );
		$image->setImageFormat( 'avif' );

		$this->imagickStripImage( $image );

		if ( $width > 0 ) {
			$image->resizeImage( $width, 0, Imagick::FILTER_CATROM, 1 );
		}

		return $image->writeImages( sprintf( 'avif:%s', $outPath ), true );
	}

	/**
	 * Try conversion using GD
	 *
	 * @param string $outPath
	 * @param int $width
	 * @return bool
	 */
	private function transformGD( string $outPath, int $width = -1 ): bool {
		if (
			!extension_loaded( 'gd' ) ||
			PHP_VERSION_ID < 80100 ||
			( gd_info()['AVIF Support'] ?? false ) === false
		) {
			return false;
		}

		$image = $this->createGDImage( $this->file );

		if ( $image === false ) {
			return false;
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Starting GD transform.', 'AvifTransformer', __FUNCTION__ ) );

		$this->gdImageTransparentBackground( $image );

		if ( $width > 0 ) {
			$image = $this->gdImageResize( $image, $width );
		}

		$gdResult = imageavif( $image, $outPath, $this->getConfigValue( 'WebPCompressionQualityAvif' ) );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Transform status is %d', 'WebPTransformer', __FUNCTION__, $gdResult ) );

		return $gdResult;
	}

	/**
	 * Log a warning if a transform failed
	 *
	 * @param Status $status
	 */
	private function logStatus( Status $status ): void {
		if ( !$status->isOK() && $status->getMessage()->getKey() !== 'backend-fail-alreadyexists' ) {
			wfLogWarning( sprintf( 'Extension:WebP could not write image "%s". Message: %s', $this->file->getName(), $status->getMessage() ) );
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function getFileExtension(): string {
		return 'avif';
	}

	/**
	 * @inheritDoc
	 */
	public static function getMimeType(): string {
		return 'image/avif';
	}
}
