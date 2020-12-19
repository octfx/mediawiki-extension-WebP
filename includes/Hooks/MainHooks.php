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

use ImagickException;
use JobQueueGroup;
use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\UploadCompleteHook;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use UploadBase;

class MainHooks implements UploadCompleteHook {
	/**
	 * Create a WebP version of the uploaded file
	 *
	 * @param UploadBase $uploadBase
	 */
	public function onUploadComplete( $uploadBase ): void {
		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPEnableConvertOnUpload' ) === false ) {
			return;
		}

		try {
			$transformer = new WebPTransformer( $uploadBase->getLocalFile() );
		} catch ( RuntimeException $e ) {
			return;
		}

		if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'WebPConvertInJobQueue' ) === true ) {
			JobQueueGroup::singleton()->push(
				new TransformWebPImageJob(
					$uploadBase->getTitle(),
					[
						'title' => $uploadBase->getTitle(),
					]
				)
			);

			return;
		}

		try {
			$transformer->transform();
		} catch ( ImagickException $e ) {
			wfLogWarning( $e->getMessage() );

			return;
		}
	}
}
