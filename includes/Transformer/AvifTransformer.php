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

use Exception;
use Imagick;
use ImagickException;
use ImagickPixel;
use MediaWiki\MediaWikiServices;
use MediaWiki\ProcOpenError;
use MediaWiki\Shell\Shell;
use MediaWiki\ShellDisabledError;
use RuntimeException;
use TempFSFile;

/**
 * Main class for transforming images into avif files
 * @phpcs:disable Generic.ControlStructures.DisallowYodaConditions.Found
 */
class AvifTransformer extends AbstractBaseTransformer {
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
	 * Check if all required extensions and dependencies are met for image transformation
	 *
	 * @return bool
	 * @throws RuntimeException
	 */
	public static function checkExtensionsLoaded(): bool {
		return ( ( extension_loaded( 'imagick' ) && !empty( Imagick::queryformats( 'AVIF' ) ) ) ||
			( extension_loaded( 'gd' ) && ( gd_info()['AVIF Support'] ?? false ) === true ) );
	}

	/**
	 * @param TempFSFile|string $outPath
	 * @param int $width
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	protected function transformImage( $outPath, int $width = -1 ): bool {
		if ( $outPath instanceof TempFSFile ) {
			$outPath = $outPath->getPath();
		}

		$avifencResult = $this->transformAvifenc( $outPath, $width );

		if ( !$avifencResult ) {
			$imagickResult = $this->transformImagick( $outPath, $width );

			if ( !$imagickResult ) {
				return $this->transformGD( $outPath, $width );
			}
		}

		return true;
	}

	/**
	 * @param string $outPath
	 * @param int $width
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	private function transformAvifenc( string $outPath, int $width = -1 ): bool {
		if (
			Shell::isDisabled() ||
			!is_executable( $this->getConfigValue( 'WebPAvifencLocation' ) ) ||
			// avifenc can't rescale images, so we need a preliminary step
			( !extension_loaded( 'imagick' ) && $width > 0 )
		) {
			return false;
		}

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Starting avifenc transform.', self::class, __FUNCTION__ )
		);

		$tempFile = null;

		if ( $width > 0 ) {
			$tempFile = $this->getTempFile();
			$image = new Imagick( $this->file->getLocalRefPath() );
			$image->resizeImage( $width, 0, Imagick::FILTER_CATROM, 1 );
			$image->writeImages( $tempFile->getPath(), true );
		}

		$command = MediaWikiServices::getInstance()->getShellCommandFactory()->create();

		// Based on https://github.com/spatie/image-optimizer
		$command->unsafeParams(
			[
				$this->getConfigValue( 'WebPAvifencLocation' ),
				'-a cq-level=18',
				'-j all',
				'--min 0',
				'--max 63',
				'--minalpha 0',
				'--minalpha 63',
				'-a end-usage=q',
				'-a tune=ssim',
				$width > 0 ? $tempFile->getPath() : $this->file->getLocalRefPath(),
				$outPath,
			]
		);

		try {
			$result = $command->execute();
		} catch ( ProcOpenError | ShellDisabledError | Exception $e ) {
			wfLogWarning( $e->getMessage() );

			return false;
		}

		wfDebugLog(
			'WebP',
			sprintf(
				'[%s::%s] Transform status is %d',
				self::class,
				__FUNCTION__,
				$result->getExitCode()
			)
		);

		return $result->getExitCode() === 0;
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
		$image->setCompression( Imagick::COMPRESSION_UNDEFINED );

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

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Starting GD transform.', self::class, __FUNCTION__ )
		);

		$this->gdImageTransparentBackground( $image );

		if ( $width > 0 ) {
			$image = $this->gdImageResize( $image, $width );
		}

		$gdResult = imageavif( $image, $outPath, $this->getConfigValue( 'WebPCompressionQualityAvif' ) );

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Transform status is %d', self::class, __FUNCTION__, $gdResult )
		);

		return $gdResult;
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
