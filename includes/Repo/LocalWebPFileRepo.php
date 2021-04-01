<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Repo;

class LocalWebPFileRepo extends \LocalRepo {

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
		$isWebP = false;

		if ( strpos( $zone, 'webp-' ) !== false ) {
			$isWebP = true;
			$zone = str_replace( 'webp-', '', $zone );
		}

		[ $container, $base ] = $this->getZoneLocation( $zone );

		if ( $container === null || $base === null ) {
			return null;
		}

		$backendName = $this->backend->getName();

		if ( $base !== '' ) { // may not be set
			$base = "/{$base}";
		}

		if ( $isWebP ) {
		   $container = sprintf( '%s/webp', $container );
		}

		return "mwstore://$backendName/{$container}{$base}";
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function fileExists( $file ) {
		$base = str_replace( '-webp', '', $file );

		return ( parent::fileExists( $base ) || parent::fileExists( $file ) );
	}
}
