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

use Config;
use FileBackendError;
use JobQueueGroup;
use MediaWiki\Extension\PictureHtmlSupport\Hook\PictureHtmlSupportBeforeProduceHtml;
use MediaWiki\Extension\WebP\TransformImageJob;
use MediaWiki\Hook\LocalFilePurgeThumbnailsHook;
use RepoGroup;
use ThumbnailImage;

class ThumbnailHooks implements LocalFilePurgeThumbnailsHook, PictureHtmlSupportBeforeProduceHtml {

	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @var RepoGroup
	 */
	private $repoGroup;

	/**
	 * @var JobQueueGroup
	 */
	private $jobQueueGroup;

	/**
	 * ThumbnailHooks constructor.
	 *
	 * @param Config $mainConfig
	 * @param RepoGroup $repoGroup
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct( Config $mainConfig, RepoGroup $repoGroup, JobQueueGroup $jobQueueGroup ) {
		$this->mainConfig = $mainConfig;
		$this->repoGroup = $repoGroup;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Clean old thumbs
	 * This is taken from LocalFile.php
	 *
	 * @inheritDoc
	 */
	public function onLocalFilePurgeThumbnails( $file, $archiveName, $urls ): void {
		$dir = $file->getThumbPath();
		$backend = $file->getRepo()->getBackend();
		$files = [];

		try {
			$iterator = $backend->getFileList( [ 'dir' => $dir ] );
			if ( $iterator !== null ) {
				foreach ( $iterator as $thumbnail ) {
					if ( strpos( $thumbnail, '.webp' ) !== false ) {
						$files[] = $thumbnail;
					}
				}
			}
		} catch ( FileBackendError $e ) {
		} // suppress (T56674)

		$purgeList = [];
		foreach ( $files as $thumbFile ) {
			$purgeList[] = "{$dir}/{$thumbFile}";
		}

		$file->getRepo()->quickPurgeBatch( $purgeList );
		$file->getRepo()->quickCleanDir( $dir );
	}

	/**
	 * Add webp versions to the page output
	 *
	 * @param ThumbnailImage $thumbnail
	 * @param array $sources
	 * @return void
	 */
	public function onPictureHtmlSupportBeforeProduceHtml( ThumbnailImage $thumbnail, array &$sources ): void {
		// File does not exist or is external
		if ( $thumbnail->getStoragePath() === false && $thumbnail->getFile()->getPath() === false ) {
			return;
		}

		$repo = $this->repoGroup->getLocalRepo();
		$hash = $thumbnail->getFile()->getHashPath();

		if ( empty( $hash ) ) {
			return;
		}

		foreach ( $this->mainConfig->get( 'EnabledTransformers' ) as $transformer ) {
			$dir = $transformer::getDirName();

			if ( $thumbnail->fileIsSource() ) {
				$url = str_replace( '/images/', sprintf( '/images/%s/', $dir ), $transformer::changeExtension( $thumbnail->getUrl() ) );

				$path = $repo->getZonePath( 'public' );

				$filePath = explode( $hash, $thumbnail->getFile()->getPath() );
				$filePath = array_pop( $filePath );
			} else {
				$url = str_replace( '/images/thumb/', sprintf( '/images/thumb/%s/', $dir ), $transformer::changeExtension( $thumbnail->getUrl() ) );

				$path = $repo->getZonePath( 'thumb' );

				$filePath = explode( $hash, $thumbnail->getStoragePath() );
				$filePath = array_pop( $filePath );

				$srcset = [
					$url
				];

				if ( $this->mainConfig->get( 'ResponsiveImages' ) ) {
					// Add higher resolutions to the srcset
					foreach ( [ 1.5, 2 ] as $resolution ) {
						$res = (int)( $thumbnail->getWidth() * $resolution );
						$resUrl = str_replace( (string)$thumbnail->getWidth(), (string)$res, $url );

						if ( $this->mainConfig->get( 'WebPEnableResponsiveVersionJobs' ) === true ) {
							$this->jobQueueGroup->push( new TransformImageJob(
								null,
								[
									'title' => $thumbnail->getFile()->getTitle(),
									'transformer' => $transformer,
									'width' => $res,
								]
							) );
						}

						$srcset[] = sprintf( '%s %sx', $resUrl, $resolution );
					}
				}

				$url = implode( ', ', $srcset );
			}

			$path = sprintf( '%s/%s/%s%s', $path, $dir, $hash, $transformer::changeExtension( $filePath ) );

			// Check if the transformed source version exists in the repo
			// If not, a job will be dispatched
			if ( !$repo->fileExists( $path ) ) {
				$params = [
					'title' => $thumbnail->getFile()->getTitle(),
					'transformer' => $transformer,
				];

				if ( !$thumbnail->fileIsSource() ) {
					$params += [
						'width' => $thumbnail->getWidth(),
						'height' => $thumbnail->getHeight(),
					];
				}

				$this->jobQueueGroup->push( new TransformImageJob( null, $params ) );
				continue;
			}

			// The transformed file exists and is added to the output
			$sources[ $transformer::getMimeType() ] = [
				'srcset' => $url,
				'type' => $transformer::getMimeType(),
				'width' => $thumbnail->getWidth(),
				'height' => $thumbnail->getHeight(),
			];
		}
	}
}
