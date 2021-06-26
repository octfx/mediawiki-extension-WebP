<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Repo;

use LocalFile;
use MediaHandler;
use MediaWiki\Extension\WebP\WebPMediaHandler;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\MediaWikiServices;
use ThumbnailImage;

class LocalWebPFile extends LocalFile {

	/**
	 * @return bool|MediaHandler
	 */
	public function getHandler() {
		if ( !in_array( $this->getMimeType(), WebPTransformer::$supportedMimes ) ) {
			return parent::getHandler();
		}

		if ( $this->handler !== null ) {
			return $this->handler;
		}

		$this->handler = new WebPMediaHandler();

		return $this->handler;
	}

	public function getExtension() {
		if ( in_array( $this->getMimeType(), WebPTransformer::$supportedMimes ) ) {
			return 'webp';
		}

		return parent::getExtension();
	}

	public function transform( $params, $flags = 0 ) {
		$transformed = parent::transform( $params, $flags );

		if ( $transformed === false ) {
			return $transformed;
		}

		$url = $this->getUrl();
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'ThumbnailScriptPath' ) !== false ) {
			$url = $transformed->getUrl();
		}

		return new ThumbnailImage( $this, $url, $this->getThumbPath( $this->thumbName( $params ) ), [
			'width' => $transformed->getWidth(),
			'height' => $transformed->getHeight(),
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

	public function getThumbUrl( $suffix = false ) {
		if ( !in_array( $this->getMimeType(), WebPTransformer::$supportedMimes ) ) {
			return parent::getThumbUrl( $suffix );
		}

		$ext = $this->getExtension();
		$path = $this->repo->getZoneUrl( 'webp-thumb', $ext ) . '/' . $this->getUrlRel();
		if ( $suffix !== false ) {
			$path .= '/' . rawurlencode( $suffix );
		}

		return $path;
	}

	public function getThumbPath( $suffix = false ) {
		if ( $suffix !== false ) {
			$suffix = explode( '.', $suffix );

			array_pop( $suffix );

			$suffix[] = 'webp';

			$suffix = implode( '.', $suffix );
		}

		// throw new \Exception(json_encode($this->repo->getZonePath( 'thumb' ) . '/webp/' . $this->getThumbRel( $suffix )));
		return $this->repo->getZonePath( 'thumb' ) . '/webp/' . $this->getThumbRel( $suffix );
	}
}
