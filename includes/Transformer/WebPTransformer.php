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

use ConfigException;
use Exception;
use File;
use FileRepo;
use Imagick;
use ImagickException;
use ImagickPixel;
use MediaWiki\MediaWikiServices;
use MediaWiki\ProcOpenError;
use MediaWiki\Shell\Shell;
use MediaWiki\ShellDisabledError;
use RuntimeException;
use Status;
use TempFSFile;

/**
 * Main class for transforming images into webp files
 */
class WebPTransformer implements MediaTransformer {

	/**
	 * Supported files
	 *
	 * @var string[]
	 */
	public static $supportedMimes = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		// 'image/gif',
	];

	/**
	 * @var File
	 */
	private $file;

	/**
	 * @var array
	 */
	private $options;

	public function __construct( File $file, array $options = [] ) {
		$this->checkImagickInstalled();

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
		$tempFile = $this->getTempFile();

		$out = $this->file->getThumbRel(
			sprintf(
				'%dpx-%s',
				$width,
				self::changeExtension( $this->file->getName() )
			)
		);

		$out = sprintf( '%s/%s', self::getDirName(), $out );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Out path is: %s', 'WebPTransformer', __FUNCTION__, $out ) );

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
		$tempFile = $this->getTempFile();

		$out = self::changeExtension( $this->file->getRel() );
		$out = sprintf( '%s/%s', self::getDirName(), $out );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Out path is: %s', 'WebPTransformer', __FUNCTION__, $out ) );

		if ( $this->checkFileExists( $out, 'public' ) && !$this->shouldOverwrite() ) {
			wfDebugLog( 'WebP', sprintf( '[%s::%s] File exists, skipping transform', 'WebPTransformer', __FUNCTION__ ) );

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
		return sprintf( '%s.webp', trim( substr( $path, 0, -( strlen( pathinfo( $path, PATHINFO_EXTENSION ) ) ) ), '.' ) );
	}

	/**
	 * @param File $file
	 * @return bool
	 */
	public static function canTransform( File $file ): bool {
		return in_array( $file->getMimeType(), self::$supportedMimes );
	}

	/**
	 * Check if Imagick is installed
	 *
	 * @throws RuntimeException
	 */
	private function checkImagickInstalled(): void {
		if ( !extension_loaded( 'imagick' ) && !extension_loaded( 'gd' ) ) {
			throw new RuntimeException( 'Extension:WebP requires Imagick or GD' );
		}
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
		if ( Shell::isDisabled() ) {
			return false;
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Starting cwebp transform.', 'WebPTransformer', __FUNCTION__ ) );

		$command = MediaWikiServices::getInstance()->getShellCommandFactory()->create();

		$resize = '';

		if ( $width > 0 ) {
			$resize = sprintf( '-resize %d 0', $width );
		}

		$command->unsafeParams(
			[
				'cwebp',
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

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Transform status is %d', 'WebPTransformer', __FUNCTION__, $result->getExitCode() ) );

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
		if ( !extension_loaded( 'imagick' ) ) {
			return false;
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Starting Imagick transform.', 'WebPTransformer', __FUNCTION__ ) );

		$image = new Imagick( $this->file->getLocalRefPath() );

		$image->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );

		$image = $image->mergeImageLayers( Imagick::LAYERMETHOD_MERGE );
		$image->setCompression( Imagick::COMPRESSION_JPEG );

		$image->setImageCompressionQuality( $this->getConfigValue( 'WebPCompressionQuality' ) );
		$image->setImageFormat( 'webp' );
		$image->setOption( 'webp:method', '6' );
		$image->setOption( 'webp:low-memory', 'true' );
		$image->setOption( 'webp:auto-filter', $this->getConfigValue( 'WebPAutoFilter' ) ? 'true' : 'false' );
		$image->setOption( 'webp:alpha-quality', (string)$this->getConfigValue( 'WebPFilterStrength' ) );

		$profiles = $image->getImageProfiles( 'icc', true );

		$image->stripImage();

		if ( !empty( $profiles ) ) {
			$image->profileImage( 'icc', $profiles['icc'] );
		}

		if ( $width > 0 ) {
			$image->resizeImage( $width, 0, Imagick::FILTER_CATROM, 1 );
		}

		$imagickResult = $image->writeImages( sprintf( 'webp:%s', $outPath ), true );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Transform status is %d', 'WebPTransformer', __FUNCTION__, $imagickResult ) );

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
		if ( !extension_loaded( 'gd' ) ) {
			return false;
		}

		switch ( $this->file->getMimeType() ) {
			case 'image/jpg':
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $this->file->getLocalRefPath() );
				break;

			case 'image/png':
				$image = imagecreatefrompng( $this->file->getLocalRefPath() );
				break;

			case 'image/gif':
				$image = imagecreatefromgif( $this->file->getLocalRefPath() );
				break;

			default:
				return false;
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Starting GD transform.', 'WebPTransformer', __FUNCTION__ ) );

		if ( $width > 0 ) {
			$originalWidth = imagesx( $image );
			$originalHeight = imagesy( $image );
			$aspectRatio = $originalWidth / $originalHeight;

			$height = (int)( $width / $aspectRatio );

			$out = imagecreatetruecolor( $width, $height );

			imagecopyresampled( $out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight );
		}

		imagepalettetotruecolor( $image );
		$gdResult = imagewebp( $image, $outPath, $this->getConfigValue( 'WebPCompressionQuality' ) );

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
	 * Get a temp file for storing transformations
	 *
	 * @return TempFSFile
	 */
	private function getTempFile(): TempFSFile {
		$tempFSFile = MediaWikiServices::getInstance()->getTempFSFileFactory()->newTempFSFile( 'transform_', 'webp' );

		if ( $tempFSFile === null ) {
			throw new RuntimeException( 'Could not get a new temp file' );
		}

		return $tempFSFile;
	}

	/**
	 * Check if a given file exists at a given zone
	 *
	 * @param string $path
	 * @param string $zone
	 *
	 * @return bool
	 */
	private function checkFileExists( string $path, string $zone ): bool {
		$root = $this->file->getRepo()->getZonePath( $zone );

		return MediaWikiServices::getInstance()
			->getRepoGroup()
			->getLocalRepo()
			->fileExists( "$root/$path" ) ?? false;
	}

	/**
	 * Loads a config value for a given key from the main config
	 * Returns null on if an ConfigException was thrown
	 *
	 * @param string $key The config key
	 *
	 * @return mixed|null
	 */
	private function getConfigValue( string $key ) {
		try {
			$value = MediaWikiServices::getInstance()->getMainConfig()->get( $key );
		} catch ( ConfigException $e ) {
			wfLogWarning(
				sprintf(
					'Could not get config for "$wg%s". %s',
					$key,
					$e->getMessage()
				)
			);
			$value = null;
		}

		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public static function getDirName(): string {
		return 'webp';
	}

	/**
	 * @inheritDoc
	 */
	public static function getMimeType(): string {
		return 'image/webp';
	}
}
