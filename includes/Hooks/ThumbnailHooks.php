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

use FileBackendError;
use MediaWiki\Extension\PictureHtmlSupport\Hook\PictureHtmlSupportBeforeProduceHtml;
use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\LocalFilePurgeThumbnailsHook;
use MediaWiki\MediaWikiServices;
use RepoGroup;
use ThumbnailImage;

class ThumbnailHooks implements LocalFilePurgeThumbnailsHook, PictureHtmlSupportBeforeProduceHtml {

	/**
	 * @var RepoGroup
	 */
	private $repoGroup;

	/**
	 * ThumbnailHooks constructor.
	 *
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( RepoGroup $repoGroup ) {
		$this->repoGroup = $repoGroup;
	}

	/**
	 * Clean old webp thumbs
	 * This is taken from LocalFile.php
	 *
	 * @inheritDoc
	 */
	public function onLocalFilePurgeThumbnails( $file, $archiveName, $urls ): void {
		$dir = $file->getThumbPath();
		$backend = $file->getRepo()->getBackend();
		$files = [];

		try {
			$iterator = $backend->getFileList( [ 'dir' => $dir ] );
			if ( $iterator !== null ) {
				foreach ( $iterator as $thumbnail ) {
					if ( strpos( $thumbnail, '.webp' ) !== false ) {
						$files[] = $thumbnail;
					}
				}
			}
		} catch ( FileBackendError $e ) {
		} // suppress (T56674)

		$purgeList = [];
		foreach ( $files as $thumbFile ) {
			$purgeList[] = "{$dir}/{$thumbFile}";
		}

		$file->getRepo()->quickPurgeBatch( $purgeList );
		$file->getRepo()->quickCleanDir( $dir );
	}

	public function onPictureHtmlSupportBeforeProduceHtml( ThumbnailImage $thumbnail, array &$sources ): void {
		if ( $thumbnail->getStoragePath() === false ) {
			return;
		}

		$repo = $this->repoGroup->getLocalRepo();

		$pathLocal = WebPTransformer::changeExtensionWebp( $thumbnail->getStoragePath() );

		$pathLocal = str_replace( [ 'local-public', 'local-thumb' ], [ 'local-public/webp', 'local-thumb/webp' ], $pathLocal );

		$pathLocal = WebPTransformer::changeExtensionWebp( $pathLocal );

		if ( !$repo->fileExists( $pathLocal ) ) {
			$job = new TransformWebPImageJob( $thumbnail->getFile()->getTitle(), [
				'title' => $thumbnail->getFile()->getTitle(),
				'width' => $thumbnail->getWidth(),
				'height' => $thumbnail->getHeight(),
			] );

			$group = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup();

			$group->push( $job );
		} else {
			if ( $thumbnail->fileIsSource() ) {
				$srcset = str_replace( '/images/', '/images/webp/', WebPTransformer::changeExtensionWebp( $thumbnail->getUrl() ) );
			} else {
				$srcset = str_replace( '/images/thumb/', '/images/thumb/webp/', WebPTransformer::changeExtensionWebp( $thumbnail->getUrl() ) );
			}

			$sources[] = [
				'srcset' => $srcset,
				'type' => 'image/webp',
				'width' => $thumbnail->getWidth(),
				'height' => $thumbnail->getHeight(),
			];
		}
	}
}
