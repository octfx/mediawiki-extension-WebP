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
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class RemoveCreatedFiles extends Maintenance {
	/**
	 *
	 */
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Removes generated files' );
		$this->addOption( 'thumbs', 'Remove all generated thumbs' );
		$this->addOption( 'images', 'Remove all generated images' );
		$this->addOption( 'force', 'Do the actual deletion.' );
		$this->setBatchSize( 100 );

		$this->requireExtension( 'WebP' );
	}

	/**
	 * @return void
	 */
	public function execute() {
		$repo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();
		$config = MediaWikiServices::getInstance()->getMainConfig();

		foreach ( $config->get( 'EnabledTransformers' ) as $transformer ) {
			$images = $repo->getZonePath( 'public' ) . '/' . $transformer::getDirName();
			$thumbs = $repo->getZonePath( 'thumb' ) . '/' . $transformer::getDirName();

			if ( $this->getOption( 'thumbs' ) !== null ) {
				$this->output( "Removing thumbnails\n" );
				$delete = $this->getOption( 'force' ) !== null;

				$files = $repo->getBackend()->getFileList( [
					'dir' => $thumbs
				] );

				$this->delete( $files, $thumbs, $delete );

				$repo->quickCleanDir( $thumbs );
			}

			if ( $this->getOption( 'images' ) !== null ) {
				$this->output( "Removing images\n" );
				$delete = $this->getOption( 'force' ) !== null;

				$files = $repo->getBackend()->getFileList( [
					'dir' => $images
				] );

				$this->delete( $files, $images, $delete );

				$repo->quickCleanDir( $images );
			}
		}
	}

	/**
	 * @param array $files
	 * @param string $backend
	 * @param bool $delete
	 * @return void
	 */
	private function delete( $files, $backend, bool $delete = false ): void {
		$repo = MediaWikiServices::getInstance()->getRepoGroup()->getLocalRepo();

		foreach ( $files as $thumb ) {
			if ( !$delete ) {
				$this->output( sprintf( "[DRY RUN] Deleting %s\n", $thumb ) );
			} else {
				$this->output( sprintf( "Deleting %s\n", $thumb ) );
				$repo->quickPurge( sprintf( '%s/%s', $backend, $thumb ) );

				$dir = explode( '/', $thumb );
				array_pop( $dir );
				$repo->quickCleanDir( sprintf( '%s/%s', $backend, implode( '/', $dir ) ) );

				array_pop( $dir );
				$repo->quickCleanDir( sprintf( '%s/%s', $backend, implode( '/', $dir ) ) );
			}
		}
	}
}

$maintClass = RemoveCreatedFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
