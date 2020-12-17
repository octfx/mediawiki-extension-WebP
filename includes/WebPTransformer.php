<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP;

use ConfigException;
use File;
use Imagick;
use MediaTransformOutput;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use Status;

/**
 * Main class for transforming images into webp files
 */
class WebPTransformer {

	/**
	 * Supported files
	 *
	 * @var string[]
	 */
	private static $supportedMimes = [
		'image/gif',
		'image/jpeg',
		'image/jpg',
		'image/png',
	];

	/**
	 * @var File
	 */
	private $file;

	public function __construct( File $file ) {
		$this->checkImagickInstalled();

		if ( !in_array( $file->getMimeType(), self::$supportedMimes ) ) {
			throw new RuntimeException(
				'Mimetype "%s" is not in supported mime: [%s]',
				$file->getMimeType(),
				implode( ', ', self::$supportedMimes )
			);
		}

		$this->file = $file;
	}

	/**
	 * Create a webp image based on thumbnail dimensions
	 *
	 * @param MediaTransformOutput $thumb
	 *
	 * @return Status
	 */
	public function transformLikeThumb( MediaTransformOutput $thumb ): Status {
		$tempFile = $this->getTempFilePath();

		$img = $this->prepare();
		$img->resizeImage( (int)$thumb->getWidth(), (int)$thumb->getHeight(), 22, 1, true );
		$img->writeImage( sprintf( 'webp:%s', $tempFile ) );

		$status = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->store(
			$tempFile,
			'thumb',
			sprintf(
				'%s/%s/%dpx-%s',
				$this->file->getHashPath(),
				$this->file->getName(),
				$thumb->getWidth(),
				self::changeExtensionWebp( $this->file->getName() )
			)
		);

		$this->logStatus( $status );

		return $status;
	}

	/**
	 * Transform the original file to a webp one
	 *
	 * @return Status
	 */
	public function transform(): Status {
		$tempFile = $this->getTempFilePath();

		$img = $this->prepare();
		$img->writeImage( sprintf( 'webp:%s', $tempFile ) );

		$status = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->store(
			$tempFile,
			'public',
			sprintf(
				'%s/%s',
				$this->file->getHashPath(),
				self::changeExtensionWebp( $this->file->getName() )
			)
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
	public static function changeExtensionWebp( string $path ): string {
		return str_replace(
			pathinfo( $path, PATHINFO_EXTENSION ),
			'webp',
			$path
		);
	}

	/**
	 * Check if Imagick is installed
	 *
	 * @throws RuntimeException
	 */
	private function checkImagickInstalled(): void {
		if ( !extension_loaded( 'imagick' ) ) {
			throw new RuntimeException( 'Extension:WebP requires Imagick' );
		}
	}

	/**
	 * Prepare to transform the image
	 * Options are configurable
	 *
	 * @return Imagick
	 * @throws \ImagickException
	 */
	private function prepare(): Imagick {
		$image = new Imagick( $this->file->getLocalRefPath() );

		$image->setCompressionQuality( $this->getConfigValue( 'WebPCompressionQuality' ) );

		$image->setOption( 'alpha-compression', '1' );
		$image->setOption( 'auto-filter', $this->getConfigValue( 'WebPAutoFilter' ) ? '1' : '0' );
		$image->setOption( 'filter-strength', (string)$this->getConfigValue( 'WebPFilterStrength' ) );
		$image->setOption( 'filter-type', '1' );

		return $image;
	}

	/**
	 * Log a warning if a transform failed
	 *
	 * @param Status $status
	 */
	private function logStatus( Status $status ): void {
		if ( !$status->isOK() ) {
			wfLogWarning( sprintf( 'Extension:WebP could not write image "%s"', $this->file->getName() ) );
		}
	}

	/**
	 * Get a temp file path for storing transformations
	 *
	 * @return string
	 */
	private function getTempFilePath(): string {
		$tempFSFile = MediaWikiServices::getInstance()->getTempFSFileFactory()->newTempFSFile( 'webp', 'webp' );

		if ( $tempFSFile === null ) {
			throw new RuntimeException( 'Could not get a new temp file' );
		}

		return $tempFSFile->getPath();
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
}
