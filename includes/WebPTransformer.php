<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP;

use ConfigException;
use Exception;
use File;
use FileRepo;
use Imagick;
use ImagickException;
use ImagickPixel;
use MediaTransformOutput;
use MediaWiki\MediaWikiServices;
use MediaWiki\ProcOpenError;
use MediaWiki\Shell\Shell;
use MediaWiki\ShellDisabledError;
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
	public static $supportedMimes = [
		// 'image/gif', -- Animations wont work
		'image/jpeg',
		'image/jpg',
		'image/png',
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

		if ( !in_array( $file->getMimeType(), self::$supportedMimes ) ) {
			throw new RuntimeException(
				'Mimetype "%s" is not in supported mime: [%s]',
				$file->getMimeType(),
				implode( ', ', self::$supportedMimes )
			);
		}

		$this->file = $file;
		$this->options = $options;
	}

	/**
	 * Create a webp image based on thumbnail dimensions
	 *
	 * @param MediaTransformOutput $thumb
	 *
	 * @return Status
	 *
	 * @throws ImagickException
	 */
	public function transformLikeThumb( MediaTransformOutput $thumb ): Status {
		$tempFile = $this->getTempFilePath();

		$out = sprintf(
			'%s%s/%dpx-%s',
			$this->file->getHashPath(),
			$this->file->getName(),
			$thumb->getWidth(),
			self::changeExtensionWebp( $this->file->getName() )
		);

		if ( $this->checkFileExists( $out, 'public' ) && !$this->shouldOverwrite() ) {
			return Status::newGood();
		}

		$result = $this->transformImage( $tempFile, (int)$thumb->getWidth() );

		if ( !$result ) {
			return Status::newFatal( 'Could not convert Image' );
		}

		$status = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->store(
			$tempFile,
			'thumb',
			$out,
			$this->shouldOverwrite() ? FileRepo::OVERWRITE : 0
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
		$tempFile = $this->getTempFilePath();

		$out = sprintf(
			'%s/%s',
			$this->file->getHashPath(),
			self::changeExtensionWebp( $this->file->getName() )
		);

		if ( $this->checkFileExists( $out, 'public' ) && !$this->shouldOverwrite() ) {
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
			$this->shouldOverwrite() ? FileRepo::OVERWRITE : 0
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
	 * Check if the overwrite flag was set
	 *
	 * @return bool
	 */
	private function shouldOverwrite(): bool {
		return isset( $this->options['overwrite'] );
	}

	/**
	 * @param string $sourcePath
	 * @param string $outPath
	 * @param int $width
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	private function transformImage( string $outPath, int $width = -1 ): bool {
		$cwebpResult = $this->transformCwebp( $outPath, $width );

		if ( !$cwebpResult ) {
			return $this->transformImagick( $outPath, $width );
		}

		return true;
	}

	/**
	 * @param string $sourcePath
	 * @param string $outPath
	 * @param int $width
	 *
	 * @return bool
	 */
	private function transformCwebp( string $outPath, int $width = -1 ): bool {
		if ( Shell::isDisabled() ) {
			return false;
		}

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

		return $result->getExitCode() === 0;
	}

	/**
	 * Prepare to transform the image
	 * Options are configurable
	 *
	 * @param string $sourcePath Path to source image
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	private function transformImagick( string $outPath, int $width = -1 ): bool {
		$image = new Imagick( $this->file->getLocalRefPath() );

		$image->setImageBackgroundColor( new ImagickPixel( 'transparent' ) );

		$image = $image->mergeImageLayers( Imagick::LAYERMETHOD_MERGE );
		$image->setCompression( Imagick::COMPRESSION_JPEG );

		$image->setCompressionQuality( $this->getConfigValue( 'WebPCompressionQuality' ) );
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
			$image->resizeImage( (int)$width, 0, Imagick::FILTER_CATROM, 1 );
		}

		return $image->writeImage( sprintf( 'webp:%s', $outPath ) );
	}

	/**
	 * Log a warning if a transform failed
	 *
	 * @param Status $status
	 */
	private function logStatus( Status $status ): void {
		if ( !$status->isOK() ) {
			wfLogWarning( sprintf( 'Extension:WebP could not write image "%s". Message: %s', $this->file->getName(), $status->getMessage() ) );
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
			->fileExists( "$root/$path" );
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
