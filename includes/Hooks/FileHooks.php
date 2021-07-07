<?php

declare( strict_types=1 );

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

namespace MediaWiki\Extension\WebP\Hooks;

use Config;
use ConfigException;
use ImagickException;
use JobQueueGroup;
use LocalFile;
use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileTransformedHook;
use MediaWiki\Hook\PageMoveCompletingHook;
use RepoGroup;
use RuntimeException;

class FileHooks implements FileTransformedHook, FileDeleteCompleteHook, PageMoveCompletingHook {

	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @var RepoGroup
	 */
	private $repoGroup;

	/**
	 * FileHooks constructor.
	 *
	 * @param Config $mainConfig
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( Config $mainConfig, RepoGroup $repoGroup ) {
		$this->mainConfig = $mainConfig;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * Creates a webp version of an image after upload was completed
	 *
	 * @inheritDoc
	 */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ): void {
		$oldPath = sprintf( 'mwstore://local-backend/local-public/webp/%s', $file->getHashPath() );
		$oldThumbPath = sprintf( 'mwstore://local-backend/local-public/thumb/webp/%s', $file->getHashPath() );

		$repo = $this->repoGroup->getLocalRepo();

		$oldThumbs = $repo->getBackend()->getFileList( [
			'dir' => $oldThumbPath
		] );

		foreach ( $oldThumbs as $oldThumb ) {
			$repo->quickPurge( sprintf( '%s/%s', $oldThumbPath, ltrim( $oldThumb, '/' ) ) );
		}

		$repo->quickPurge( sprintf( '%s/%s', $oldPath, WebPTransformer::changeExtensionWebp( $file->getName() ) ) );

		$repo->quickCleanDir( sprintf( '%s/%s', $oldThumbPath, ltrim( $file->getName(), '/' ) ) );
		$repo->quickCleanDir( $oldPath );
		$repo->quickCleanDir( $oldThumbPath );
	}

	/**
	 * For each created thumbnail well create a webp version
	 *
	 * @inheritDoc
	 */
	public function onFileTransformed( $file, $thumb, $tmpThumbPath, $thumbPath ): void {
		try {
			if ( $this->mainConfig->get( 'WebPEnableConvertOnTransform' ) === false || $this->mainConfig->get( 'ThumbnailScriptPath' ) !== false ) {
				return;
			}
		} catch ( ConfigException $e ) {
			return;
		}

		if ( !WebPTransformer::canTransform( $file ) ) {
			return;
		}

		try {
			$transformer = new WebPTransformer( $file, [ 'overwrite' => true, ] );
		} catch ( RuntimeException $e ) {
			return;
		}

		try {
			if ( $this->mainConfig->get( 'WebPConvertInJobQueue' ) === true ) {
				JobQueueGroup::singleton()->push(
					new TransformWebPImageJob(
						$file->getTitle(),
						[
							'title' => $file->getTitle(),
							'width' => $thumb->getWidth(),
							'height' => $thumb->getHeight(),
							'overwrite' => true,
						]
					)
				);

				return;
			}
		} catch ( ConfigException $e ) {
			return;
		}

		try {
			$transformer->transformLikeThumb( $thumb );
		} catch ( ImagickException $e ) {
			wfLogWarning( $e->getMessage() );

			return;
		}
	}

	/**
	 * We'll move the webp version of a file after a page move completes
	 *
	 * @inheritDoc
	 */
	public function onPageMoveCompleting( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
		$repo = $this->repoGroup->getLocalRepo();

		$oldFile = $repo->newFile(
			 $old->getText()
		);

		$newFile = $repo->newFile(
			$new->getText()
		);

		if ( $newFile === null || $oldFile === null ) {
			return;
		}

		$oldPath = WebPTransformer::changeExtensionWebp( str_replace( 'local-public', 'local-public/webp', $oldFile->getPath() ) );
		$newPath = WebPTransformer::changeExtensionWebp( str_replace( 'local-public', 'local-public/webp', $newFile->getPath() ) );

		$repo->getBackend()->prepare( [
			'dir' => $this->getDirPath( $newPath )
		] );

		$status = $repo->getBackend()->move(
			[
				'src' => $oldPath,
				'dst' => $newPath,
			]
		);

		if ( !$status->isOK() ) {
			wfLogWarning( json_encode( $status->getErrors() ) );
		}

		$repo->quickPurge( $this->getDirPath( $oldPath ) );
		$repo->quickCleanDir( $this->getDirPath( $oldPath ) );

		$this->moveThumbs(
			$oldFile,
			$newFile
		);
	}

	private function getDirPath( string $filePath ): string {
		$path = explode( '/', $filePath );
		array_pop( $path );

		return implode( '/', $path );
	}

	private function moveThumbs( LocalFile $oldFile, LocalFile $newFile ): void {
		$repo = $this->repoGroup->getLocalRepo();

		$newPath = sprintf( 'mwstore://local-backend/local-public/thumb/webp/%s', $newFile->getHashPath() );
		$oldPath = sprintf( 'mwstore://local-backend/local-public/thumb/webp/%s', $oldFile->getHashPath() );

		$oldName = explode( '/', $oldFile->getPath() );
		$oldName = array_pop( $oldName );
		$ending = pathinfo( $oldName, PATHINFO_EXTENSION );
		$oldName = str_replace( $ending, '', $oldName );

		$newName = explode( '/', $newFile->getPath() );
		$newName = array_pop( $newName );
		$ending = pathinfo( $newName, PATHINFO_EXTENSION );
		$newName = str_replace( $ending, '', $newName );

		$repo->getBackend()->prepare( [
			'dir' => sprintf( '%s%s%s', $newPath, ltrim( $newName, '/' ), $ending )
		] );

		$files = $repo->getBackend()->getFileList( [
			'dir' => $oldPath
		] );

		foreach ( $files as $file ) {
			$repo->getBackend()->move(
				[
					'src' => sprintf( '%s%s', $oldPath, $file ),
					'dst' => sprintf( '%s%s', $newPath, str_replace( $oldName, $newName, $file ) ),
				]
			);
		}

		$repo->quickPurge( $oldPath );
		$repo->quickCleanDir( $oldPath );
	}
}
