<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Repo;

class LocalWebPFile extends \LocalFile {

/*
	public function getUrl()
	{



		if (!isset($this->url)) {
			$this->assertRepoDefined();
			$ext = $this->getExtension();
			$this->url = $this->repo->getZoneUrl('public', $ext) . '/' . $this->getUrlRel();
		}

		return $this->url;

	}

	*/
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
