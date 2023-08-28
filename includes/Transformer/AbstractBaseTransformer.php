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

use Config;
use ConfigException;
use File;
use FileRepo;
use GdImage;
use Imagick;
use ImagickException;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use Status;
use TempFSFile;

/**
 * Base Transformer Class
 */
abstract class AbstractBaseTransformer implements MediaTransformer {
	/**
	 * @var File
	 */
	protected File $file;

	/**
	 * @var Config
	 */
	protected Config $config;

	/**
	 * @var array
	 */
	protected $options;

	/**
	 * Supported files
	 *
	 * @var string[]
	 */
	public static $supportedMimes = [];

	/**
	 * @param File $file
	 * @param array $options
	 */
	public function __construct( File $file, array $options = [] ) {
		if ( !static::canTransform( $file ) ) {
			throw new RuntimeException(
				sprintf(
					'Mimetype "%s" is not in supported mime: [%s]',
					$file->getMimeType(),
					implode( ', ', static::$supportedMimes )
				)
			);
		}

		$this->file = $file;
		$this->options = $options;
		$this->config = MediaWikiServices::getInstance()->getMainConfig();
	}

	/**
	 * Transform the original file
	 * Does not run the transformation if the file already exists and the overwrite param is set to false
	 *
	 * @return Status
	 *
	 * @throws ImagickException When imagick is used and the image could not be loaded
	 * @throws RuntimeException If no temp file could be created
	 */
	public function transform(): Status {
		$tempFile = $this->getTempFile();

		$out = static::changeExtension( $this->file->getRel() );
		$out = sprintf( '%s/%s', static::getFileExtension(), $out );

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Out path is: %s', static::class, __FUNCTION__, $out )
		);

		if ( $this->checkFileExists( $out, 'public' ) && !$this->shouldOverwrite() ) {
			wfDebugLog(
				'WebP',
				sprintf( '[%s::%s] File exists, skipping transform', static::class, __FUNCTION__ )
			);

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
	 * Transforms the image to a given thumbnail width
	 *
	 * @param int $width
	 * @return Status
	 *
	 * @throws ImagickException When imagick is used and the image could not be loaded
	 * @throws RuntimeException If no temp file could be created
	 */
	public function transformLikeThumb( int $width ): Status {
		$tempFile = $this->getTempFile();

		$out = $this->file->getThumbRel(
			sprintf(
				'%dpx-%s',
				$width,
				static::changeExtension( $this->file->getName() )
			)
		);

		$out = sprintf( '%s/%s', static::getFileExtension(), $out );

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Out path is: %s', static::class, __FUNCTION__, $out )
		);

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
	 * Change the last part of a path to the extension of this transformer
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public static function changeExtension( string $path ): string {
		return sprintf(
			'%s.%s',
			trim( substr( $path, 0, -( strlen( pathinfo( $path, PATHINFO_EXTENSION ) ) ) ), '.' ),
			static::getFileExtension()
		);
	}

	/**
	 * Check if a file can be transformed by this transformer
	 *
	 * @param File $file
	 * @return bool
	 */
	public static function canTransform( File $file ): bool {
		return in_array( $file->getMimeType(), static::$supportedMimes ) && static::checkExtensionsLoaded();
	}

	/**
	 * Check if all required extensions and dependencies are met for image transformation
	 *
	 * @return bool
	 * @throws RuntimeException
	 */
	public static function checkExtensionsLoaded(): bool {
		throw new RuntimeException( 'Method must be overridden' );
	}

	/**
	 * @throws RuntimeException
	 */
	public static function getFileExtension(): string {
		throw new RuntimeException( 'Method must be overridden' );
	}

	/**
	 * @param TempFSFile|string $outPath
	 * @param int $width
	 *
	 * @return bool
	 * @throws RuntimeException
	 */
	protected function transformImage( $outPath, int $width = -1 ): bool {
		throw new RuntimeException( 'Method must be overridden' );
	}

	/**
	 * Get a temp file for storing transformations
	 *
	 * @return TempFSFile
	 * @throws RuntimeException
	 */
	protected function getTempFile(): TempFSFile {
		$tempFSFile = MediaWikiServices::getInstance()
			->getTempFSFileFactory()
			->newTempFSFile( 'transform_', static::getFileExtension() );

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
	protected function checkFileExists( string $path, string $zone ): bool {
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
	protected function getConfigValue( string $key ): mixed {
		try {
			$value = $this->config->get( $key );
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
	 * Creates a GdImage based on a given image
	 * Returns false on an unsupported mime type
	 *
	 * @param File $file
	 * @return GdImage|false
	 */
	protected function createGDImage( File $file ): GdImage | false {
		switch ( $file->getMimeType() ) {
			case 'image/jpg':
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $file->getLocalRefPath() );
				break;

			case 'image/png':
				$image = imagecreatefrompng( $file->getLocalRefPath() );
				break;

			case 'image/gif':
				$image = imagecreatefromgif( $file->getLocalRefPath() );
				break;

			case 'image/webp':
				$image = imagecreatefromwebp( $file->getLocalRefPath() );
				break;

			default:
				return false;
		}

		return $image;
	}

	/**
	 * Adds a transparent background to a GdImage
	 *
	 * @param GdImage $image
	 * @return void
	 */
	protected function gdImageTransparentBackground( GdImage $image ): void {
		imagepalettetotruecolor( $image );
		imagealphablending( $image, true );
		imagesavealpha( $image, true );

		$transparency = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		imagefill( $image, 0, 0, $transparency );
	}

	/**
	 * Resizes a GdImage to a given width
	 *
	 * @param GdImage $image
	 * @param int $width
	 * @return GdImage
	 */
	protected function gdImageResize( GdImage $image, int $width ): GdImage {
		$originalWidth = imagesx( $image );
		$originalHeight = imagesy( $image );
		$aspectRatio = $originalWidth / $originalHeight;

		$height = (int)( $width / $aspectRatio );

		$out = imagecreatetruecolor( $width, $height );
		$this->gdImageTransparentBackground( $out );

		imagecopyresampled(
			$out,
			$image,
			0,
			0,
			0,
			0,
			$width,
			$height,
			$originalWidth,
			$originalHeight
		);

		return $out;
	}

	/**
	 * Strips all image information and restores possible icc profiles
	 *
	 * @param Imagick $image
	 * @return void
	 * @throws ImagickException
	 */
	protected function imagickStripImage( Imagick $image ): void {
		$profiles = $image->getImageProfiles( 'icc' );

		$image->stripImage();

		if ( !empty( $profiles ) ) {
			$image->profileImage( 'icc', $profiles['icc'] );
		}
	}

	/**
	 * Check if the overwrite flag was set
	 *
	 * @return bool
	 */
	protected function shouldOverwrite(): bool {
		return ( $this->options['overwrite'] ?? false ) === true;
	}

	/**
	 * Log a warning if a transform failed
	 *
	 * @param Status $status
	 */
	protected function logStatus( Status $status ): void {
		if ( !$status->isOK() && $status->getMessage()->getKey() !== 'backend-fail-alreadyexists' ) {
			wfLogWarning(
				sprintf(
					'Extension:WebP could not write image "%s". Message: %s',
					$this->file->getName(),
					$status->getMessage()
				)
			);
		}
	}
}
