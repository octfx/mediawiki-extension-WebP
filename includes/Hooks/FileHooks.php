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

use JobQueueGroup;
use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileTransformedHook;
use MediaWiki\Hook\FileUndeleteCompleteHook;
use MediaWiki\MediaWikiServices;
use RuntimeException;

class FileHooks implements FileTransformedHook, FileDeleteCompleteHook, FileUndeleteCompleteHook {

	/**
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
	 * @inheritDoc
	 *
	 * TODO
	 */
	public function onFileUndeleteComplete( $title, $fileVersions, $user, $reason ): void {
		// TODO: Implement onFileUndeleteComplete() method.
	}

	/**
	 * For each created thumbnail well create a webp version
	 *
	 * @inheritDoc
	 */
	public function onFileTransformed( $file, $thumb, $tmpThumbPath, $thumbPath ): void {
		try {
			$transformer = new WebPTransformer( $file );
		} catch ( RuntimeException $e ) {
			wfLogWarning( $e->getMessage() );

			return;
		}

		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPConvertInJobQueue' ) === true ) {
			JobQueueGroup::singleton()->lazyPush(
				new TransformWebPImageJob(
					'createWebPImageThumbJob',
					[
						'title' => $file->getTitle(),
						'likeThumb' => true,
						'width' => $thumb->getWidth(),
						'height' => $thumb->getHeight(),
					]
				)
			);

			return;
		}

		$transformer->transformLikeThumb( $thumb );
	}
}
