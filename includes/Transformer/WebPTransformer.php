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
use TempFSFile;

/**
 * Main class for transforming images into webp files
 * @phpcs:disable Generic.ControlStructures.DisallowYodaConditions.Found
 */
class WebPTransformer extends AbstractBaseTransformer {
	/**
	 * Supported files
	 *
	 * @var string[]
	 */
	public static $supportedMimes = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		// MW generates png/jpg thumbs for webp files
		'image/webp',
		// 'image/gif',
	];

	/**
	 * Check if all required extensions and dependencies are met for image transformation
	 *
	 * @return bool
	 */
	public static function checkExtensionsLoaded(): bool {
		return ( extension_loaded( 'imagick' ) && !empty( Imagick::queryformats( 'WebP' ) ) ) ||
			( extension_loaded( 'gd' ) && ( gd_info()['WebP Support'] ?? false ) === true ) ||
			(
				!Shell::isDisabled() &&
				is_executable( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPCWebPLocation' ) )
			);
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

		$cwebpResult = $this->transformCwebp( $outPath, $width );

		if ( !$cwebpResult ) {
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
	 */
	private function transformCwebp( string $outPath, int $width = -1 ): bool {
		if ( Shell::isDisabled() || !is_executable( $this->getConfigValue( 'WebPCWebPLocation' ) ) ) {
			return false;
		}

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Starting cwebp transform.', self::class, __FUNCTION__ )
		);

		$command = MediaWikiServices::getInstance()->getShellCommandFactory()->create();

		$resize = '';

		if ( $width > 0 ) {
			$resize = sprintf( '-resize %d 0', $width );
		}

		$command->unsafeParams(
			[
				$this->getConfigValue( 'WebPCWebPLocation' ),
				'-quiet',
				$resize,
				sprintf( '-q %d', $this->getConfigValue( 'WebPCompressionQuality' ) ),
				sprintf( '-alpha_q %d', $this->getConfigValue( 'WebPFilterStrength' ) ),
				$this->getConfigValue( 'WebPAutoFilter' ) ? '-af' : '',
				$this->file->getLocalRefPath(),
				sprintf( '-o %s', $outPath ),
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
		if ( !extension_loaded( 'imagick' ) || empty( Imagick::queryformats( 'WebP' ) ) ) {
			return false;
		}

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Starting Imagick transform.', self::class, __FUNCTION__ )
		);

		$image = new Imagick( $this->file->getLocalRefPath() );

		$image->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );

		$image = $image->mergeImageLayers( Imagick::LAYERMETHOD_MERGE );
		$image->setCompression( Imagick::COMPRESSION_JPEG );

		$image->setCompressionQuality( $this->getConfigValue( 'WebPCompressionQuality' ) );
		$image->setImageCompressionQuality( $this->getConfigValue( 'WebPCompressionQuality' ) );
		$image->setImageFormat( 'webp' );
		$image->setOption( 'webp:method', '6' );
		$image->setOption( 'webp:low-memory', 'true' );
		$image->setOption( 'webp:auto-filter', $this->getConfigValue( 'WebPAutoFilter' ) ? 'true' : 'false' );
		$image->setOption( 'webp:alpha-quality', (string)$this->getConfigValue( 'WebPFilterStrength' ) );

		$this->imagickStripImage( $image );

		if ( $width > 0 ) {
			$image->resizeImage( $width, 0, Imagick::FILTER_CATROM, 1 );
		}

		$imagickResult = $image->writeImages( sprintf( 'webp:%s', $outPath ), true );

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Transform status is %d', self::class, __FUNCTION__, $imagickResult )
		);

		return $imagickResult;
	}

	/**
	 * Try conversion using GD
	 *
	 * @param string $outPath
	 * @param int $width
	 * @return bool
	 */
	private function transformGD( string $outPath, int $width = -1 ): bool {
		if ( !extension_loaded( 'gd' ) || ( gd_info()['WebP Support'] ?? false ) === false ) {
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

		$gdResult = imagewebp( $image, $outPath, $this->getConfigValue( 'WebPCompressionQuality' ) );

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
		return 'webp';
	}

	/**
	 * @inheritDoc
	 */
	public static function getMimeType(): string {
		return 'image/webp';
	}
}
