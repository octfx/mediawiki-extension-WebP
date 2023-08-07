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
use ConfigException;
use ExtensionRegistry;
use JobQueueGroup;
use MediaWiki\Extension\WebP\TransformImageJob;
use MediaWiki\Hook\FileUndeleteCompleteHook;
use MediaWiki\Hook\UploadCompleteHook;
use RuntimeException;
use UploadBase;

class MainHooks implements UploadCompleteHook, FileUndeleteCompleteHook {

	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @var JobQueueGroup
	 */
	private $jobQueueGroup;

	/**
	 * FileHooks constructor.
	 *
	 * @param Config $mainConfig
	 * @param JobQueueGroup $jobQueueGroup
	 */
	public function __construct( Config $mainConfig, JobQueueGroup $jobQueueGroup ) {
		$this->mainConfig = $mainConfig;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * Check various config values
	 */
	public static function setup(): void {
		global $wgHashedUploadDirectory;

		if ( $wgHashedUploadDirectory !== true ) {
			throw new RuntimeException( 'Extension:WebP requires $wgHashedUploadDirectory to be true' );
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'AWS' ) ) {
			global $wgAWSRepoHashLevels;

			if ( $wgAWSRepoHashLevels == 0 ) {
				throw new RuntimeException( 'Extension:WebP requires $wgAWSRepoHashLevels to be non zero' );
			}
		}
	}

	/**
	 * Create a WebP version of the uploaded file
	 *
	 * @param UploadBase $uploadBase
	 */
	public function onUploadComplete( $uploadBase ): void {
		try {
			if ( $this->mainConfig->get( 'WebPEnableConvertOnUpload' ) === false ) {
				return;
			}
		} catch ( ConfigException $e ) {
			return;
		}

		if ( $uploadBase->getLocalFile() === null ) {
			return;
		}

		foreach ( $this->mainConfig->get( 'EnabledTransformers' ) as $transformer ) {
			$this->jobQueueGroup->push(
				new TransformImageJob(
					null,
					[
						'title' => $uploadBase->getTitle(),
						'transformer' => $transformer,
					]
				)
			);
		}
	}

	/**
	 * Create webp files after un-deletion
	 *
	 * @param $title
	 * @param $fileVersions
	 * @param $user
	 * @param $reason
	 * @return void
	 */
	public function onFileUndeleteComplete( $title, $fileVersions, $user, $reason ): void {
		foreach ( $this->mainConfig->get( 'EnabledTransformers' ) as $transformer ) {
			$this->jobQueueGroup->push(
				new TransformImageJob(
					null,
					[
						'title' => $title,
						'transformer' => $transformer,
					]
				)
			);
		}
	}
}
