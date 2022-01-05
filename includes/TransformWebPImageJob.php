<?php

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

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP;

use Exception;
use Job;
use MediaWiki\MediaWikiServices;
use RuntimeException;
use Title;

/**
 * Creates webp images through the JobQueue
 */
class TransformWebPImageJob extends Job {
	protected $removeDuplicates = true;

	public function __construct( ?Title $title, array $params ) {
		parent::__construct( 'TransformWebPImage', $title, $params );
	}

	/**
	 * Actually transform the file
	 *
	 * @return bool
	 */
	public function run(): bool {
		if ( !is_array( $this->params ) ) {
			$this->setLastError( 'Extension:WebP: Params is not an array.' );

			return false;
		}

		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->params['title'] );

		if ( !$file || !$file->exists() ) {
			$this->setLastError( sprintf( 'Extension:WebP: File "%s" does not exist', $this->params['title'] ) );

			return false;
		}

		try {
			$transformer = new WebPTransformer( $file, [ 'overwrite' => $this->params['overwrite'] ?? false ] );
		} catch ( RuntimeException $e ) {
			$this->setLastError( $e->getMessage() );
			return false;
		}

		try {
			if ( isset( $this->params['width'] ) ) {
				$fakeThumb = new FakeMediaTransformOutput( (int)$this->params['width'], (int)$this->params['height'] );

				$status = $transformer->transformLikeThumb( $fakeThumb );
			} else {
				$status = $transformer->transform();
			}
		} catch ( Exception $e ) {
			$this->setLastError( $e->getMessage() );

			return false;
		}

		if ( !$status->isOK() ) {
			$this->setLastError( $status->getMessage() );

			return false;
		}

		return true;
	}
}
