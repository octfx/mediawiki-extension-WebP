<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Repo;

use LocalFile;

class LocalWebPFile extends LocalFile {

	public function getPath() {
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
}
