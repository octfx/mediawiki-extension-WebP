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
use FileBackendError;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\LocalFilePurgeThumbnailsHook;
use MediaWiki\Hook\ThumbnailBeforeProduceHTMLHook;
use RepoGroup;
use RequestContext;
use ThumbnailImage;

class ThumbnailHooks implements LocalFilePurgeThumbnailsHook, ThumbnailBeforeProduceHTMLHook {
	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @var RepoGroup
	 */
	private $repoGroup;

	/**
	 * ThumbnailHooks constructor.
	 *
	 * @param Config $mainConfig
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( Config $mainConfig, RepoGroup $repoGroup ) {
		$this->mainConfig = $mainConfig;
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

	/**
	 * Change out the image link with a webp one, if the browser supports webp, and a local webp file exists
	 * If the image contains the class 'no-webp' the original image will be returned
	 *
	 * @inheritDoc
	 */
	public function onThumbnailBeforeProduceHTML( $thumbnail, &$attribs, &$linkAttribs ): void {
		$request = RequestContext::getMain();

		if ( $this->shouldSkipThumbnailHook( $thumbnail, $request ) ) {
			wfDebugLog( 'WebP', sprintf( '[%s::%s] Skipping ThumbnailHook for "%s"', 'ThumbnailHooks', __FUNCTION__, $thumbnail->getUrl() ) );

			return;
		}

		if ( isset( $attribs['class'] ) && strpos( $attribs['class'], 'no-webp' ) !== false ) {
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

		// TODO: Investigate
		str_replace( '/webp/webp', '/webp', $webP );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Path local is "%s"; WebP Url is "%s"', 'ThumbnailHooks', __FUNCTION__, $pathLocal, $webP ) );

		if ( $this->repoGroup->getLocalRepo()->fileExists( $pathLocal ) ) {
			$attribs['src'] = $webP;
		}
	}

	/**
	 * Skip the hook if the file in question can't be transformed,
	 * the thumbnail has no image or no url
	 *
	 * or if the accept header should be checked and it does not contain webp
	 *
	 * @param ThumbnailImage $thumbnail
	 * @param ?RequestContext $request
	 * @return bool True if hook should be skipped
	 */
	private function shouldSkipThumbnailHook( ThumbnailImage $thumbnail, ?RequestContext $request ): bool {
		if ( !WebPTransformer::canTransform( $thumbnail->getFile() ) ||
			$thumbnail->getFile() === false ||
			$thumbnail->getUrl() === false ||
			strpos( $thumbnail->getUrl(), 'thumb.php' ) !== false
		) {
			return true;
		}

		try {
			$accept = $request === null ? '' : $request->getRequest()->getHeader( 'ACCEPT' );
			if ( $this->mainConfig->get( 'WebPCheckAcceptHeader' ) === true && strpos( $accept, 'image/webp' ) === false ) {
				return true;
			}
		} catch ( ConfigException $e ) {
			//
		}

		return false;
	}
}
