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
class TransformImageJob extends Job {
	protected $removeDuplicates = true;

	public function __construct( ?Title $title, array $params ) {
		parent::__construct( 'TransformImage', $params );
	}

	/**
	 * Actually transform the file
	 *
	 * @return bool
	 */
	public function run(): bool {
		if ( !is_array( $this->params ) || !isset( $this->params['transformer'] ) ) {
			$this->setLastError( 'Extension:WebP: Params is not an array.' );

			return false;
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Running transform job for transformer %s', 'TransformImageJob', __FUNCTION__, $this->params['transformer'] ) );

		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->params['title'] );

		if ( !$file || !$file->exists() ) {
			$this->setLastError( sprintf( 'Extension:WebP: File "%s" does not exist', $this->params['title'] ) );

			return false;
		}

		if ( !$this->params['transformer']::canTransform( $file ) ) {
			return true;
		}

		try {
			$transformer = new $this->params['transformer']( $file, [ 'overwrite' => $this->params['overwrite'] ?? false ] );
		} catch ( RuntimeException $e ) {
			$this->setLastError( $e->getMessage() );
			return false;
		}

		try {
			if ( isset( $this->params['width'] ) ) {
				$status = $transformer->transformLikeThumb( (int)$this->params['width'] );
			} else {
				$status = $transformer->transform();
			}
		} catch ( Exception $e ) {
			$this->setLastError( $e->getMessage() );

			return false;
		}

		if ( !$status->isOK() && $status->getMessage()->getKey() !== 'backend-fail-alreadyexists' ) {
			$this->setLastError( $status->getMessage() );
			return false;
		}

		wfDebugLog( 'WebP', sprintf( '[%s::%s] Transform success', 'TransformImageJob', __FUNCTION__ ) );

		return true;
	}
}
