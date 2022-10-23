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

namespace MediaWiki\Extension\WebP\Repo;

use LocalFile;
use MediaHandler;
use MediaTransformError;
use MediaTransformOutput;
use MediaWiki\Extension\WebP\WebPMediaHandler;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MWException;
use ThumbnailImage;

class LocalWebPFile extends LocalFile {
	/**
	 * A nasty hack to short-circuit to the parent if file is in delete mode
	 *
	 * @var bool
	 */
	public static $deleteCalled = false;

	/**
	 * Returns the correct media handler if the current file can be transformed to WebP
	 *
	 * @return bool|MediaHandler
	 */
	public function getHandler() {
		if ( self::$deleteCalled || !WebPTransformer::canTransform( $this ) ) {
			return parent::getHandler();
		}

		if ( $this->handler !== null && $this->handler instanceof WebPMediaHandler ) {
			return $this->handler;
		}

		$this->handler = new WebPMediaHandler();

		return $this->handler;
	}

	/**
	 * Changes the extension to webp if the file is supported
	 *
	 * @return string
	 */
	public function getExtension() {
		if ( self::$deleteCalled || !WebPTransformer::canTransform( $this ) ) {
			return parent::getExtension();
		}

		return 'webp';
	}

    /**
     * Nasty way of setting a flag if a file is to be deleted
     * @inheritDoc
     */
	public function deleteFile( $reason, UserIdentity $user, $suppress = false ) {
		self::$deleteCalled = true;
		return parent::deleteFile( $reason, $user, $suppress );
	}

	/**
	 * Get the transformed image
	 *
	 * @param array $params
	 * @param int $flags
	 * @return bool|MediaTransformError|MediaTransformOutput|ThumbnailImage
	 */
	public function transform( $params, $flags = 0 ) {
		$handler = $this->getHandler();

		if ( is_bool( $handler ) ) {
			return $handler;
		}

		$normalized = $params;
		$continue = $handler->normaliseParams( $this, $normalized );

		$greaterThanSource = ( $normalized['physicalWidth'] ?? $normalized['width'] ?? 0 ) > $this->getWidth( $normalized['page'] ?? 1 ) ||
			( $normalized['physicalHeight'] ?? $normalized['height'] ?? 0 ) > $this->getHeight( $normalized['page'] ?? 1 );

		if ( !$continue || ( $greaterThanSource && WebPTransformer::canTransform( $this ) ) ) {
			wfDebugLog(
				'WebP',
				sprintf( '[%s::%s] Returning client scaling image for "%s"', 'LocalWebPFile', __FUNCTION__, $this->getName() )
			);

			// Return the original file url if continue is false (which indicates that the transform should fail)
			return new ThumbnailImage( $this, $this->getUrl( !$continue ), null, [
				'width' => $normalized['clientWidth'],
				'height' => $normalized['clientHeight']
			] );
		}

		$transformed = parent::transform( $normalized, $flags );

		if ( $transformed === false || !WebPTransformer::canTransform( $this ) ) {
			wfDebugLog(
				'WebP',
				sprintf( '[%s::%s] Returning parent transform for "%s"', 'LocalWebPFile', __FUNCTION__, $this->getName() )
			);

			return $transformed;
		}

		$thumbName = $this->thumbName( $normalized );

		$url = $this->getThumbUrl( $thumbName );
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'ThumbnailScriptPath' ) !== false ) {
			$url = $transformed->getUrl();
		}

		$path = $this->getThumbPath( $this->thumbName( $normalized ) );

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Returning webp transform for "%s"', 'LocalWebPFile', __FUNCTION__, $this->getName() ),
			'all',
			[
				'url' => $url,
				'path' => $path,
			]
		);

		return new ThumbnailImage( $this, $url, $path, [
			'width' => $transformed->getWidth(),
			'height' => $transformed->getHeight(),
		] );
	}

	/**
	 * Forces webp file to download
	 * Sets the name of the downloaded file
	 *
	 * @param string $thumbName
	 * @param string $dispositionType
	 * @return string
	 */
	public function getThumbDisposition( $thumbName, $dispositionType = 'inline' ) {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getThumbDisposition( $thumbName, $dispositionType );
		}

		$parts = [
			'attachment',
			"filename*=UTF-8''" . rawurlencode( basename( WebPTransformer::changeExtensionWebp( $thumbName ) ) ),
		];

		return implode( ';', $parts );
	}

	/**
	 * Returns the local file path
	 *
	 * @return bool|string
	 * @throws MWException
	 */
	public function getPath() {
		if ( self::$deleteCalled || !WebPTransformer::canTransform( $this ) ) {
			return parent::getPath();
		}

		$zone = 'webp-public';

		if ( $this->repo->fileExists( $this->repo->getZonePath( $zone ) . '/' . $this->getRel() ) ) {
			return $this->repo->getZonePath( $zone ) . '/' . $this->getRel();
		}

		if ( !isset( $this->path ) ) {
			$this->assertRepoDefined();
			$this->path = $this->repo->getZonePath( 'public' ) . '/' . $this->getRel();
		}

		return $this->path;
	}

	/**
	 * Returns the url to the thumb
	 *
	 * @param string|false $suffix Thumb size
	 * @return string
	 */
	public function getThumbUrl( $suffix = false ) {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getThumbUrl( $suffix );
		}

		$ext = $this->getExtension();
		$url = $this->repo->getZoneUrl( 'webp-thumb', $ext ) . '/' . $this->getUrlRel();

		if ( is_array( $suffix ) ) {
			dd( $suffix );
		}
		if ( $suffix !== false ) {
			$url .= '/' . rawurlencode( WebPTransformer::changeExtensionWebp( $suffix ) );
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning thumb url "%s" for "%s"', 'LocalWebPFile', __FUNCTION__, $url, $this->getName() ) );

		return $url;
	}

	/**
	 * Returns the path to the local thumb
	 *
	 * @param string|false $suffix
	 * @return string
	 */
	public function getThumbPath( $suffix = false ) {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getThumbPath( $suffix );
		}

		if ( $suffix !== false ) {
			$suffix = WebPTransformer::changeExtensionWebp( $suffix );
		}

		$path = $this->repo->getZonePath( 'webp-thumb' ) . '/' . $this->getThumbRel( $suffix );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning thumb path "%s" for "%s"', 'LocalWebPFile', __FUNCTION__, $path, $this->getName() ) );

		return $path;
	}

	/**
	 * Returns the url to the local webp source
	 *
	 * @return string
	 */
	public function getUrl( bool $forceOriginal = false ) {
		// TODO: Check if webp file exists
		if ( self::$deleteCalled || $forceOriginal === true || !WebPTransformer::canTransform( $this ) ) {
			return parent::getUrl();
		}

		$ext = $this->getExtension();
		$url = $this->repo->getZoneUrl( 'webp-public', $ext ) . '/' . $this->getUrlRel();

		$url = WebPTransformer::changeExtensionWebp( $url );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning url "%s" for "%s"', 'LocalWebPFile', __FUNCTION__, $url, $this->getName() ) );

		return $url;
	}
}
