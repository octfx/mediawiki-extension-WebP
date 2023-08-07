<?php

namespace MediaWiki\Extension\WebP\Transformer;

use File;
use Status;

interface MediaTransformer {

	/**
	 * @param File $file The file that should be transformed
	 * @param array $options Array of options
	 */
	public function __construct( File $file, array $options = [] );

	/**
	 * Transform the image like the passed thumbnail image
	 * @param int $width Width of the thumb, height is inferred automatically
	 * @return Status
	 */
	public function transformLikeThumb( int $width ): Status;

	/**
	 * Transform the source image
	 * @return Status
	 */
	public function transform(): Status;

	/**
	 * Change the image extension to the one of the transformer, e.g. 'webp'
	 *
	 * @param string $path
	 * @return string
	 */
	public static function changeExtension( string $path ): string;

	/**
	 * Check if a file can be transformed by this transformer
	 *
	 * @param File $file
	 * @return bool
	 */
	public static function canTransform( File $file ): bool;

	/**
	 * The subdirectory name where images from this transformer are stored
	 * E.g. /images/<Folder>, or /images/thumbs/<Folder>
	 *
	 * @return string
	 */
	public static function getDirName(): string;

	/**
	 * The mime type of the transformed image
	 *
	 * @return string
	 */
	public static function getMimeType(): string;
}
