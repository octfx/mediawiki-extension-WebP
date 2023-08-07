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
use File;
use JobQueueGroup;
use LocalFile;
use MediaWiki\Extension\WebP\TransformImageJob;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileTransformedHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use RepoGroup;

class FileHooks implements FileTransformedHook, FileDeleteCompleteHook, PageMoveCompleteHook {
	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @var RepoGroup
	 */
	private $repoGroup;

	/**
	 * @var JobQueueGroup
	 */
	private $jobQueueGroup;

	/**
	 * FileHooks constructor.
	 *
	 * @param Config $mainConfig
	 * @param RepoGroup $repoGroup
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct( Config $mainConfig, RepoGroup $repoGroup, JobQueueGroup $jobQueueGroup ) {
		$this->mainConfig = $mainConfig;
		$this->repoGroup = $repoGroup;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Deletes the converted files after the source file was deleted
	 *
	 * @inheritDoc
	 */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ): void {
		$repo = $this->repoGroup->getLocalRepo();

		foreach ( $this->mainConfig->get( 'EnabledTransformers' ) as $transformer ) {
			$oldPath = sprintf( '%s/%s/%s', $repo->getZonePath( 'public' ), $transformer::getDirName(), $file->getHashPath() );
			$oldThumbPath = sprintf( '%s/%s/%s', $repo->getZonePath( 'thumb' ), $transformer::getDirName(), $file->getHashPath() );

			$oldThumbs = $repo->getBackend()->getFileList( [
				'dir' => $oldThumbPath
			] );

			foreach ( $oldThumbs as $oldThumb ) {
				$repo->quickPurge( sprintf( '%s/%s', $oldThumbPath, ltrim( $oldThumb, '/' ) ) );
			}

			$repo->quickPurge( sprintf( '%s/%s', $oldPath, $transformer::changeExtension( $file->getName() ) ) );

			$repo->quickCleanDir( sprintf( '%s/%s', $oldThumbPath, ltrim( $file->getName(), '/' ) ) );
			$repo->quickCleanDir( sprintf( '%s/%s', $repo->getZonePath( 'public' ), $transformer::getDirName() ) );
			$repo->quickCleanDir( sprintf( '%s/%s', $repo->getZonePath( 'thumb' ), $transformer::getDirName() ) );
		}
	}

	/**
	 * For each created thumbnail create a file for each active transformer
	 *
	 * @inheritDoc
	 */
	public function onFileTransformed( $file, $thumb, $tmpThumbPath, $thumbPath ): void {
		try {
			if ( $this->mainConfig->get( 'WebPEnableConvertOnTransform' ) === false ) {
				return;
			}
		} catch ( ConfigException $e ) {
			return;
		}

		foreach ( $this->mainConfig->get( 'EnabledTransformers' ) as $transformer ) {
			$this->jobQueueGroup->push(
				new TransformImageJob(
					null,
					[
						'transformer' => $transformer,
						'title' => $file->getTitle(),
						'width' => $thumb->getWidth(),
						'height' => $thumb->getHeight(),
					]
				)
			);
		}
	}

	/**
	 * We'll move the webp version of a file after a page move completes
	 *
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ) {
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

		$oldFile->load( File::READ_LATEST );
		$newFile->load( File::READ_LATEST );

		foreach ( $this->mainConfig->get( 'EnabledTransformers' ) as $transformer ) {
			$path = sprintf( '%s/%s', $repo->getZonePath( 'public' ), $transformer::getDirName() );

			$oldPath = sprintf( '%s/%s%s', $path, $oldFile->getHashPath(), $transformer::changeExtension( $oldFile->getName() ) );
			$newPath = sprintf( '%s/%s%s', $path, $newFile->getHashPath(), $transformer::changeExtension( $newFile->getName() ) );

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
		}

		$this->repoGroup->clearCache();

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
		$path = $repo->getZonePath( 'thumb' );

		foreach ( $this->mainConfig->get( 'EnabledTransformers' ) as $transformer ) {
			$newPath = sprintf( '%s/%s/%s', $path, $transformer::getDirName(), $newFile->getHashPath() );
			$oldPath = sprintf( '%s/%s/%s', $path, $transformer::getDirName(), $oldFile->getHashPath() );

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
}
