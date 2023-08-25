<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Hooks;

use MediaWiki\Extension\WebP\Transformer\TransformerFactory;
use MediaWiki\Hook\MediaWikiServicesHook;

class MediaWikiServices implements MediaWikiServicesHook {

	/**
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ): void {
		$services->defineService( 'WebPTransformerFactory', fn() => new TransformerFactory() );
	}
}
