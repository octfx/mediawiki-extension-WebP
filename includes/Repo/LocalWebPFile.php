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
use MWException;
use ThumbnailImage;

class LocalWebPFile extends LocalFile {

	/**
	 * Returns the correct media handler if the current file can be transformed to WebP
	 *
	 * @return bool|MediaHandler
	 */
	public function getHandler() {
		if ( !WebPTransformer::canTransform( $this ) ) {
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
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getExtension();
		}

		return 'webp';
	}

	/**
	 * Get the transformed image
	 *
	 * @param array $params
	 * @param int $flags
	 * @return bool|MediaTransformError|MediaTransformOutput|ThumbnailImage
	 */
	public function transform( $params, $flags = 0 ) {
		$greaterThanSource = ( $params['physicalWidth'] ?? $params['width'] ?? 0 ) >= $this->getWidth( $params['page'] ?? 1 ) ||
			( $params['physicalHeight'] ?? $params['height'] ?? 0 ) >= $this->getHeight( $params['page'] ?? 1 );

		if ( $greaterThanSource && WebPTransformer::canTransform( $this ) ) {
			if ( isset( $params['width'] ) ) {
				$params = [
					'width' => $this->getWidth( $params['page'] ?? 1 )
				];
			} elseif ( isset( $params['height'] ) ) {
				$params = [
					'height' => $this->getHeight( $params['page'] ?? 1 )
				];
			} else {
				return new ThumbnailImage( $this, $this->getUrl(), null, [
					'width' => $this->getWidth( $params['page'] ?? 1 ),
					'height' => $this->getHeight( $params['page'] ?? 1 ),
				] );
			}
		}

		$transformed = parent::transform( $params, $flags );

		if ( $transformed === false || !WebPTransformer::canTransform( $this ) ) {
			wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning parent transform for "%s"', 'LocalWebPFile', __FUNCTION__, $this->getName() ) );

			return $transformed;
		}

		$thumbName = $this->thumbName( $params );

		$url = $this->getThumbUrl( $thumbName );
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'ThumbnailScriptPath' ) !== false ) {
			$url = $transformed->getUrl();
		}

		wfDebugLog(
			'WebP',
			sprintf( '[%s::%s] Returning webp transform for "%s"', 'LocalWebPFile', __FUNCTION__, $this->getName() ),
			'all',
			[
				'url' => $url,
				'path' => $this->getThumbPath( $this->thumbName( $params ) ),
			]
		);

		return new ThumbnailImage( $this, $url, $this->getThumbPath( $this->thumbName( $params ) ), [
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
		if ( !WebPTransformer::canTransform( $this ) ) {
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
	 * @param false $suffix Thumb size
	 * @return string
	 */
	public function getThumbUrl( $suffix = false ) {
		if ( !WebPTransformer::canTransform( $this ) ) {
			return parent::getThumbUrl( $suffix );
		}

		$ext = $this->getExtension();
		$url = $this->repo->getZoneUrl( 'webp-thumb', $ext ) . '/' . $this->getUrlRel();

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
		if ( $forceOriginal === true || !WebPTransformer::canTransform( $this ) ) {
			return parent::getUrl();
		}

		$ext = $this->getExtension();
		$url = $this->repo->getZoneUrl( 'webp-public', $ext ) . '/' . $this->getUrlRel();

		$url = WebPTransformer::changeExtensionWebp( $url );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning url "%s" for "%s"', 'LocalWebPFile', __FUNCTION__, $url, $this->getName() ) );

		return $url;
	}
}
