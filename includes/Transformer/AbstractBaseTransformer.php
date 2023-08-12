<?php

namespace MediaWiki\Extension\WebP\Transformer;

use Config;
use ConfigException;
use File;
use GdImage;
use Imagick;
use ImagickException;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use TempFSFile;

abstract class AbstractBaseTransformer {

	protected File $file;

	protected Config $config;

	/**
	 * Get a temp file for storing transformations
	 *
	 * @param string $fileExtension
	 * @return TempFSFile
	 */
	protected function getTempFile( string $fileExtension ): TempFSFile {
		$tempFSFile = MediaWikiServices::getInstance()->getTempFSFileFactory()->newTempFSFile( 'transform_', $fileExtension );

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

		imagecopyresampled( $out, $image, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight );

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
}
