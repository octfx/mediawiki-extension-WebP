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
use MediaWiki\Hook\LocalFilePurgeThumbnailsHook;
use MediaWiki\Hook\ThumbnailBeforeProduceHTMLHook;
use MediaWiki\MediaWikiServices;
use RequestContext;

class ThumbnailHooks implements LocalFilePurgeThumbnailsHook, ThumbnailBeforeProduceHTMLHook {
	/**
	 * Clean old webp thumbs
	 * This is taken from LocalFile.php
	 *
	 * @inheritDoc
	 */
	public function onLocalFilePurgeThumbnails( $file, $archiveName ): void {
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

	/**
	 * Change out the image link with a webp one, if the browser supports webp, and a local webp file exists
	 * If the image contains the class 'no-webp' the original image will be returned
	 *
	 * @inheritDoc
	 */
	public function onThumbnailBeforeProduceHTML( $thumbnail, &$attribs, &$linkAttribs ): void {
		$request = RequestContext::getMain();

		if ( $request === null || $request->getRequest()->getHeader( 'ACCEPT' ) === false || strpos( $request->getRequest()->getHeader( 'ACCEPT' ), 'image/webp' ) === false ) {
			return;
		}

		if ( isset( $attribs['class'] ) && strpos( $attribs['class'], 'no-webp' ) !== false ) {
			return;
		}

		$file = $thumbnail->getFile();
		if ( $file === false ) {
			return;
		}

		$path = $thumbnail->getStoragePath();

		if ( $path === false ) {
			$path = $thumbnail->getFile()->getPath();
		}

		$webP = sprintf(
			'%swebp',
			substr( $thumbnail->getUrl(), 0, -( strlen( pathinfo( $thumbnail->getUrl(), PATHINFO_EXTENSION ) ) ) )
		);

		$pathLocal = sprintf( '%swebp', substr( $path, 0, -( strlen( pathinfo( $thumbnail->getUrl(), PATHINFO_EXTENSION ) ) ) ) );

		$pathLocal = str_replace( [ 'local-public', 'local-thumb' ], [ 'local-public/webp', 'local-thumb/webp' ], $pathLocal );

		if ( strpos( $webP, 'thumb/' ) !== false ) {
			$webP = str_replace( 'thumb/', 'thumb/webp/', $webP );
		} else {
			$webP = str_replace( 'images/', 'images/webp/', $webP );
		}

		if ( MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo()->fileExists( $pathLocal ) ) {
			$attribs['src'] = $webP;
		}
	}
}
