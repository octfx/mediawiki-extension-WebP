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
use MediaWiki\Extension\WebP\TransformWebPImageJob;
use MediaWiki\Extension\WebP\WebPTransformer;
use MediaWiki\Hook\FileUndeleteCompleteHook;
use MediaWiki\Hook\UploadCompleteHook;
use MediaWiki\MediaWikiServices;
use UploadBase;

class MainHooks implements UploadCompleteHook, FileUndeleteCompleteHook {
	public static $WEBP_PUBLIC_ZONE = 'webp-public';
	public static $WEBP_THUMB_ZONE = 'webp-thumb';

	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * FileHooks constructor.
	 *
	 * @param Config $mainConfig
	 */
	public function __construct( Config $mainConfig ) {
		$this->mainConfig = $mainConfig;
	}

	/**
	 * Adds all required zones to the local file repo
	 */
	public static function setup(): void {
		global $wgLocalFileRepo;

		$wgLocalFileRepo['zones'][self::$WEBP_PUBLIC_ZONE] = [
			'container' => 'local-public',
			'urlsByExt' => [],
			'directory' => 'webp',
		];

		$wgLocalFileRepo['zones'][self::$WEBP_THUMB_ZONE] = [
			'container' => 'local-thumb',
			'urlsByExt' => [],
			'directory' => 'webp',
		];
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

		if ( $uploadBase->getLocalFile() === null || !WebPTransformer::canTransform( $uploadBase->getLocalFile() ) ) {
			return;
		}

		$group = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup();

		$group->push(
			new TransformWebPImageJob(
				$uploadBase->getTitle(),
				[
					'title' => $uploadBase->getTitle(),
				]
			)
		);
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
	public function onFileUndeleteComplete( $title, $fileVersions, $user, $reason ) {
		$group = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup();

		$group->push(
			new TransformWebPImageJob(
				$title,
				[
					'title' => $title,
				]
			)
		);
	}
}
