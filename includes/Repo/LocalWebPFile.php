<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Repo;

use LocalFile;
use MediaHandler;
use MediaWiki\Extension\WebP\WebPMediaHandler;
use MediaWiki\Extension\WebP\WebPTransformer;
use ThumbnailImage;

class LocalWebPFile extends LocalFile {

	/**
	 * @return bool|MediaHandler
	 */
	public function getHandler() {
		if ( $this->handler !== null ) {
			return $this->handler;
		}

		if ( !in_array( $this->getMimeType(), WebPTransformer::$supportedMimes ) ) {
			return parent::getHandler();
		}

		$this->handler = new WebPMediaHandler();

		return $this->handler;
	}

	public function getExtension() {
		return 'webp';
	}

	public function transform( $params, $flags = 0 ) {
		parent::transform( $params, $flags );

		// TODO: This is such a wrong fix
		return new ThumbnailImage( $this, $this->getUrl(), $this->getThumbPath( $this->thumbName( $params ) ), [
			'width' => $params['width'],
			'height' => $params['height'] ?? 0,
		] );
	}

	public function getThumbDisposition( $thumbName, $dispositionType = 'inline' ) {
		$parts = [];

		$parts[] = 'attachment';

		$parts[] = "filename*=UTF-8''" . rawurlencode( basename( WebPTransformer::changeExtensionWebp( $thumbName ) ) );

		return implode( ';', $parts );
	}

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

	public function getThumbPath( $suffix = false ) {
		if ( $suffix !== false ) {
			$suffix = explode( '.', $suffix );

			array_pop( $suffix );

			$suffix[] = 'webp';

			$suffix = implode( '.', $suffix );
		}

		return $this->repo->getZonePath( 'thumb' ) . '/webp/' . $this->getThumbRel( $suffix );
	}

}
