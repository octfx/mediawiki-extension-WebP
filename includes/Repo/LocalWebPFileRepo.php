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

use FSFile;
use LocalRepo;
use MediaWiki\Extension\WebP\WebPTransformer;

class LocalWebPFileRepo extends LocalRepo {

	public function __construct( array $info = null ) {
		$this->fileFactory = [ LocalWebPFile::class, 'newFromTitle' ];
		$this->fileFactoryKey = [ LocalWebPFile::class, 'newFromKey' ];
		$this->fileFromRowFactory = [ LocalWebPFile::class, 'newFromRow' ];
		parent::__construct( $info );
	}

	/**
	 * Get the storage path corresponding to one of the zones
	 *
	 * @param string $zone
	 * @return string|null Returns null if the zone is not defined
	 */
	public function getZonePath( $zone ) {
		if ( strpos( $zone, 'webp-' ) === false ) {
			return parent::getZonePath( $zone );
		}

		$zone = str_replace( 'webp-', '', $zone );

		[ $container, $base ] = $this->getZoneLocation( $zone );

		if ( $container === null || $base === null ) {
			return null;
		}

		$backendName = $this->backend->getName();

		$container = sprintf( '%s/webp', $container );

		if ( $base !== '' ) { // may not be set
			$base = "/{$base}";
		}

		$path = "mwstore://$backendName/{$container}{$base}";

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning zone path "%s"', 'LocalWebPFileRepo', __FUNCTION__, $path ) );

		return $path;
	}

	/**
	 * Returns the corresponding zone url for the basic zones
	 * Appends /webp if required
	 *
	 * @inheritDoc
	 */
	public function getZoneUrl( $zone, $ext = null ) {
		$url = parent::getZoneUrl( str_replace( 'webp-', '', $zone ), $ext );

		if ( strpos( $zone, 'webp-' ) !== false ) {
			$url .= '/webp';
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning zone url "%s" for zone "%s"', 'LocalWebPFileRepo', __FUNCTION__, $url, $zone ) );

		return $url;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function fileExists( $file ): bool {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

		if ( in_array( $ext, [ 'png', 'jpg', 'jpeg' ], true ) ) {
			$file = WebPTransformer::changeExtensionWebp( $file );
		}

		$exists = parent::fileExists( $file );

		wfDebugLog( 'WebP', sprintf( '[%s::%s] File "%s" exists: %b', 'LocalWebPFileRepo', __FUNCTION__, $file, $exists ) );

		return $exists;
	}

	/**
	 * @param string $virtualUrl
	 * @return FSFile|null
	 */
	public function getLocalReference( $virtualUrl ) {
		if ( strpos( $virtualUrl, '/webp' ) !== false ) {
			$referenceWebP = parent::getLocalReference( WebPTransformer::changeExtensionWebp( $virtualUrl ) );

			if ( $referenceWebP !== null ) {
				wfDebugLog( 'WebP', sprintf( '[%s::%s] Returning local webp reference for url "%s"', 'LocalWebPFileRepo', __FUNCTION__, $virtualUrl ) );

				return $referenceWebP;
			}
		}

		return parent::getLocalReference( str_replace( '/webp', '', $virtualUrl ) );
	}
}
