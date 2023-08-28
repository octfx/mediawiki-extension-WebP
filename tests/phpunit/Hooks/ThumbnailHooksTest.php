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

namespace MediaWiki\Extension\WebP\Tests\Hooks;

use Exception;
use File;
use FileBackend;
use FileRepo;
use IForeignRepoWithMWApi;
use LocalFile;
use MediaWiki\Extension\WebP\Hooks\ThumbnailHooks;
use RepoGroup;
use ThumbnailImage;

/**
 * @group WebP
 */
class ThumbnailHooksTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\ThumbnailHooks
	 * @return void
	 * @throws Exception
	 */
	public function testConstructor() {
		$hooks = new ThumbnailHooks(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getRepoGroup(),
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$this->assertInstanceOf( ThumbnailHooks::class, $hooks );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\ThumbnailHooks::onLocalFilePurgeThumbnails
	 * @return void
	 * @throws Exception
	 */
	public function testOnLocalFilePurgeThumbnails() {
		$backendMock = $this->getMockBuilder( FileBackend::class )->disableOriginalConstructor()->getMock();
		$backendMock->expects( $this->once() )
			->method( 'getFileList' )
			->with( [ 'dir' => '<path>' ] )
			->willReturn( [
				'File1.webp',
				'File2.webp',
				'File3.webp',
			] );

		$repoMock = $this->getMockBuilder( FileRepo::class )->disableOriginalConstructor()->getMock();
		$repoMock->method( 'getBackend' )->willReturn( $backendMock );
		$repoMock->expects( $this->once() )->method( 'quickPurgeBatch' );
		$repoMock->expects( $this->once() )->method( 'quickCleanDir' );

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'getThumbPath' )->willReturn( '<path>' );
		$fileMock->expects( $this->exactly( 3 ) )->method( 'getRepo' )->willReturn( $repoMock );

		$hooks = new ThumbnailHooks(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getRepoGroup(),
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$hooks->onLocalFilePurgeThumbnails( $fileMock, '', [] );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\ThumbnailHooks::onPictureHtmlSupportBeforeProduceHtml
	 * @return void
	 * @throws Exception
	 */
	public function testOnPictureHtmlSupportBeforeProduceHtmlNoStoragePath() {
		$this->overrideConfigValues( [
			'GenerateThumbnailOnParse' => true,
		] );

		$thumbnailMock = $this->getMockBuilder( ThumbnailImage::class )->disableOriginalConstructor()->getMock();
		$thumbnailMock->expects( $this->once() )->method( 'getStoragePath' )->willReturn( false );

		$repoMock = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repoMock->expects( $this->never() )->method( 'getLocalRepo' );

		$hooks = new ThumbnailHooks(
			$this->getServiceContainer()->getMainConfig(),
			$repoMock,
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$sources = [];
		$hooks->onPictureHtmlSupportBeforeProduceHtml( $thumbnailMock, $sources );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\ThumbnailHooks::onPictureHtmlSupportBeforeProduceHtml
	 * @return void
	 * @throws Exception
	 */
	public function testOnPictureHtmlSupportBeforeProduceHtmlGenerateThumbnailOnParseFalse() {
		$this->overrideConfigValues( [
			'GenerateThumbnailOnParse' => false,
		] );

		$thumbnailMock = $this->getMockBuilder( ThumbnailImage::class )->disableOriginalConstructor()->getMock();

		$repoMock = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repoMock->expects( $this->never() )->method( 'getLocalRepo' );

		$hooks = new ThumbnailHooks(
			$this->getServiceContainer()->getMainConfig(),
			$repoMock,
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$sources = [];
		$hooks->onPictureHtmlSupportBeforeProduceHtml( $thumbnailMock, $sources );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\ThumbnailHooks::onPictureHtmlSupportBeforeProduceHtml
	 * @return void
	 * @throws Exception
	 */
	public function testOnPictureHtmlSupportBeforeProduceHtmlForeignFile() {
		$this->overrideConfigValues( [
			'GenerateThumbnailOnParse' => true,
		] );

		$foreignRepo = $this->getMockForAbstractClass( IForeignRepoWithMWApi::class );

		$fileMock = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'getRepo' )->willReturn( $foreignRepo );

		$thumbnailMock = $this->getMockBuilder( ThumbnailImage::class )->disableOriginalConstructor()->getMock();
		$thumbnailMock->expects( $this->once() )->method( 'getStoragePath' )->willReturn( '<path>' );
		$thumbnailMock->expects( $this->atLeast( 1 ) )->method( 'getFile' )->willReturn( $fileMock );

		$repoMock = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repoMock->expects( $this->never() )->method( 'getLocalRepo' );

		$hooks = new ThumbnailHooks(
			$this->getServiceContainer()->getMainConfig(),
			$repoMock,
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$sources = [];
		$hooks->onPictureHtmlSupportBeforeProduceHtml( $thumbnailMock, $sources );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\ThumbnailHooks::onPictureHtmlSupportBeforeProduceHtml
	 * @return void
	 * @throws Exception
	 */
	public function testOnPictureHtmlSupportBeforeProduceHtmlSourceWithoutPath() {
		$this->overrideConfigValues( [
			'GenerateThumbnailOnParse' => true,
		] );

		$repo = $this->getMockBuilder( FileRepo::class )->disableOriginalConstructor()->getMock();

		$fileMock = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'getRepo' )->willReturn( $repo );
		$fileMock->expects( $this->once() )->method( 'getPath' )->willReturn( false );

		$thumbnailMock = $this->getMockBuilder( ThumbnailImage::class )->disableOriginalConstructor()->getMock();
		$thumbnailMock->expects( $this->once() )->method( 'getStoragePath' )->willReturn( '<path>' );
		$thumbnailMock->expects( $this->once() )->method( 'fileIsSource' )->willReturn( true );
		$thumbnailMock->expects( $this->atLeast( 1 ) )->method( 'getFile' )->willReturn( $fileMock );

		$repoMock = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repoMock->expects( $this->never() )->method( 'getLocalRepo' );

		$hooks = new ThumbnailHooks(
			$this->getServiceContainer()->getMainConfig(),
			$repoMock,
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$sources = [];
		$hooks->onPictureHtmlSupportBeforeProduceHtml( $thumbnailMock, $sources );
	}
}
