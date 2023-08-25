<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Tests\Hooks;

use Exception;
use FileBackend;
use FileRepo;
use JobQueueGroup;
use LocalFile;
use MediaTransformOutput;
use MediaWiki\Extension\WebP\Hooks\FileHooks;
use MediaWiki\Extension\WebP\Transformer\NullTransformer;
use MediaWiki\Extension\WebP\Transformer\WebPTransformer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use RepoGroup;
use StatusValue;

/**
 * @group WebP
 */
class FileHooksTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\FileHooks
	 * @return void
	 * @throws Exception
	 */
	public function testConstructor() {
		$hooks = new FileHooks(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getRepoGroup(),
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$this->assertInstanceOf( FileHooks::class, $hooks );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\FileHooks::onFileDeleteComplete
	 * @return void
	 * @throws Exception
	 */
	public function testOnFileDeleteComplete() {
		$this->overrideConfigValues( [
			'EnabledTransformers' => [ WebPTransformer::class ],
		] );

		$backendMock = $this->getMockBuilder( FileBackend::class )->disableOriginalConstructor()->getMock();
		$backendMock->expects( $this->once() )->method( 'getFileList' )->willReturn( [ '<file>' ] );

		$repoMock = $this->getMockBuilder( FileRepo::class )->disableOriginalConstructor()->getMock();
		$repoMock->expects( $this->exactly( 2 ) )->method( 'quickPurgeBatch' );
		$repoMock->expects( $this->exactly( 3 ) )->method( 'quickCleanDir' );

		$repoMock->expects( $this->once() )->method( 'getBackend' )->willReturn( $backendMock );

		$groupMock = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$groupMock->expects( $this->once() )->method( 'getLocalRepo' )->willReturn( $repoMock );

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->exactly( 2 ) )->method( 'getHashPath' )->willReturn( '' );
		$fileMock->expects( $this->exactly( 2 ) )->method( 'getName' )->willReturn( 'foo.jpg' );

		$hooks = new FileHooks(
			$this->getServiceContainer()->getMainConfig(),
			$groupMock,
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$hooks->onFileDeleteComplete(
			$fileMock,
			null,
			null,
			$this->getServiceContainer()->getUserFactory()->newAnonymous(),
			''
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\FileHooks::onFileTransformed
	 * @return void
	 * @throws Exception
	 */
	public function testOnFileTransformedDisabled() {
		$this->overrideConfigValues( [
			'WebPEnableConvertOnTransform' => false,
		] );

		$queueMock = $this->getMockBuilder( JobQueueGroup::class )->disableOriginalConstructor()->getMock();
		$queueMock->expects( $this->never() )->method( 'push' );

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();

		$hooks = new FileHooks(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getRepoGroup(),
			$queueMock
		);

		$transformMock = $this->getMockBuilder( MediaTransformOutput::class )->disableOriginalConstructor()->getMock();

		$hooks->onFileTransformed(
			$fileMock,
			$transformMock,
			'',
			''
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\FileHooks::onFileTransformed
	 * @return void
	 * @throws Exception
	 */
	public function testOnFileTransformed() {
		$this->overrideConfigValues( [
			'WebPEnableConvertOnTransform' => true,
			'EnabledTransformers' => [ WebPTransformer::class ],
		] );

		$queueMock = $this->getMockBuilder( JobQueueGroup::class )->disableOriginalConstructor()->getMock();
		$queueMock->expects( $this->once() )->method( 'push' );

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'getTitle' )->willReturn( null );

		$hooks = new FileHooks(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getRepoGroup(),
			$queueMock
		);

		$transformMock = $this->getMockBuilder( MediaTransformOutput::class )->disableOriginalConstructor()->getMock();
		$transformMock->expects( $this->once() )->method( 'getWidth' )->willReturn( null );
		$transformMock->expects( $this->once() )->method( 'getHeight' )->willReturn( null );

		$hooks->onFileTransformed(
			$fileMock,
			$transformMock,
			'',
			''
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\FileHooks::onPageMoveComplete
	 * @covers \MediaWiki\Extension\WebP\Hooks\FileHooks::getDirPath
	 * @covers \MediaWiki\Extension\WebP\Hooks\FileHooks::moveThumbs
	 * @return void
	 * @throws Exception
	 */
	public function testOnPageMoveComplete() {
		$this->overrideConfigValues( [
			'EnabledTransformers' => [ NullTransformer::class ],
		] );

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->atLeast( 2 ) )->method( 'getName' )->willReturn( 'Foo' );
		$fileMock->expects( $this->atLeast( 2 ) )->method( 'getPath' )->willReturn( 'Foo' );

		$titleMock = $this->getMockBuilder( Title::class )->disableOriginalConstructor()->getMock();
		$titleMock->expects( $this->atLeast( 2 ) )->method( 'getText' )->willReturn( 'Foo' );

		$repoMock = $this->getMockBuilder( FileRepo::class )->disableOriginalConstructor()->getMock();
		$repoMock->expects( $this->atLeast( 1 ) )->method( 'quickPurgeBatch' );
		$repoMock->expects( $this->atLeast( 1 ) )->method( 'quickCleanDir' );
		$repoMock->expects( $this->atLeast( 2 ) )->method( 'newFile' )->willReturn( $fileMock );

		$repoMock->expects( $this->atLeast( 1 ) )->method( 'getBackend' )->willReturn( new class(){
			public function prepare() {
			}

			public function move() {
				return StatusValue::newGood();
			}

			public function getFileList() {
				return [ '<file>' ];
			}
		} );

		$groupMock = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$groupMock->expects( $this->atLeast( 1 ) )->method( 'getLocalRepo' )->willReturn( $repoMock );

		$hooks = new FileHooks(
			$this->getServiceContainer()->getMainConfig(),
			$groupMock,
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$hooks->onPageMoveComplete(
			$titleMock,
			$titleMock,
			$this->getServiceContainer()->getUserFactory()->newAnonymous(),
			0,
			0,
			'',
			$this->getMockBuilder( RevisionRecord::class )->disableOriginalConstructor()->getMock()
		);
	}
}
