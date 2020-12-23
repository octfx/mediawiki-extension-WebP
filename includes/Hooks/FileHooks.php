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

use ImagickException;
use JobQueueGroup;
use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileTransformedHook;
use MediaWiki\Hook\PageMoveCompletingHook;
use MediaWiki\MediaWikiServices;
use RuntimeException;

class FileHooks implements FileTransformedHook, FileDeleteCompleteHook, PageMoveCompletingHook {

	/**
     * Creates a webp version of an image after upload was completed
     *
	 * @inheritDoc
	 */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ): void {
		MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->quickPurgeBatch(
			[
				WebPTransformer::changeExtensionWebp( $file->getPath() ),
			]
		);

		MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->quickCleanDir(
			$file->getPath()
		);
	}

	/**
	 * For each created thumbnail well create a webp version
	 *
	 * @inheritDoc
	 */
	public function onFileTransformed( $file, $thumb, $tmpThumbPath, $thumbPath ): void {
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPEnableConvertOnTransform' ) === false ) {
			return;
		}

		if ( !in_array( $file->getMimeType(), WebPTransformer::$supportedMimes ) ) {
			return;
		}

		try {
			$transformer = new WebPTransformer( $file, [ 'overwrite' => true, ] );
		} catch ( RuntimeException $e ) {
			return;
		}

		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPConvertInJobQueue' ) === true ) {
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
		$oldFile = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile(
			 $old->getText()
		);

		$newFile = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->newFile(
			$new->getText()
		);

		if ( $newFile === null || $oldFile === null ) {
			return;
		}

		$status = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->getBackend()->move(
			[
				'src' => WebPTransformer::changeExtensionWebp( $oldFile->getPath() ),
				'dst' => WebPTransformer::changeExtensionWebp( $newFile->getPath() ),
			]
		);

		if ( !$status->isOK() ) {
			wfLogWarning( json_encode( $status->getErrors() ) );
		}
	}
}
