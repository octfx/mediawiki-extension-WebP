<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Tests;

use Exception;
use InvalidArgumentException;
use LocalFile;
use MediaWiki\Extension\WebP\Transformer\NullTransformer;
use MediaWiki\Extension\WebP\Transformer\TransformerFactory;
use MediaWiki\Extension\WebP\Transformer\WebPTransformer;
use MediaWiki\Extension\WebP\TransformImageJob;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use RepoGroup;
use Status;

/**
 * @group WebP
 */
class TransformImageJobTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob
	 * @return void
	 */
	public function testConstructor() {
		$job = new TransformImageJob( Title::newFromText( 'Foo' ), [] );

		$this->assertInstanceOf( TransformImageJob::class, $job );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 */
	public function testRunMissingTransformer() {
		$job = new TransformImageJob( Title::newFromText( 'Foo' ), [] );

		$this->assertFalse( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 */
	public function testRunFileNotExist() {
		$job = new TransformImageJob(
			null,
			[
				'title' => 'Foo',
				'transformer' => NullTransformer::class,
			]
		);

		$this->assertFalse( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 * @throws Exception
	 */
	public function testRunCantTransform() {
		NullTransformer::$canTransform = false;

		$job = new TransformImageJob(
			null,
			[
				'title' => 'Foo',
				'transformer' => NullTransformer::class,
			]
		);

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'exists' )->willReturn( true );

		$repo = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repo->expects( $this->once() )
			->method( 'findFile' )
			->willReturn( $fileMock );

		$this->setService( 'RepoGroup', $repo );

		$this->assertTrue( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 * @throws Exception
	 */
	public function testRunTransformerException() {
		NullTransformer::$canTransform = true;

		$job = new TransformImageJob(
			null,
			[
				'title' => 'Foo',
				'transformer' => NullTransformer::class,
			]
		);

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'exists' )->willReturn( true );

		$repo = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repo->expects( $this->once() )
			->method( 'findFile' )
			->willReturn( $fileMock );

		$factoryMock = $this->getMockBuilder( TransformerFactory::class )->getMock();
		$factoryMock->method( 'getInstance' )->willThrowException( new InvalidArgumentException() );

		$this->setService( 'RepoGroup', $repo );
		$this->setService( 'WebPTransformerFactory', $factoryMock );

		$this->assertFalse( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 * @throws Exception
	 */
	public function testRunTransform() {
		NullTransformer::$transformStatus = Status::newGood();
		NullTransformer::$canTransform = true;

		$job = new TransformImageJob(
			null,
			[
				'title' => 'Foo',
				'transformer' => NullTransformer::class,
			]
		);

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'exists' )->willReturn( true );

		$repo = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repo->expects( $this->once() )
			->method( 'findFile' )
			->willReturn( $fileMock );

		$factoryMock = $this->getMockBuilder( TransformerFactory::class )->getMock();
		$factoryMock->expects( $this->once() )
			->method( 'getInstance' )
			->willReturn( new NullTransformer( $fileMock, [] ) );

		$this->setService( 'RepoGroup', $repo );
		$this->setService( 'WebPTransformerFactory', $factoryMock );

		$this->assertTrue( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 * @throws Exception
	 */
	public function testRunTransformFail() {
		// Just _a_ status
		NullTransformer::$transformStatus = Status::newFatal( 'backend-fail-stream', '<no path>' );
		NullTransformer::$canTransform = true;

		$job = new TransformImageJob(
			null,
			[
				'title' => 'Foo',
				'transformer' => NullTransformer::class,
			]
		);

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'exists' )->willReturn( true );

		$repo = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repo->expects( $this->once() )
			->method( 'findFile' )
			->willReturn( $fileMock );

		$factoryMock = $this->getMockBuilder( TransformerFactory::class )->getMock();
		$factoryMock->expects( $this->once() )
			->method( 'getInstance' )
			->willReturn( new NullTransformer( $fileMock, [] ) );

		$this->setService( 'RepoGroup', $repo );
		$this->setService( 'WebPTransformerFactory', $factoryMock );

		$this->assertFalse( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 * @throws Exception
	 */
	public function testRunTransformException() {
		NullTransformer::$canTransform = true;

		$job = new TransformImageJob(
			null,
			[
				'title' => 'Foo',
				'transformer' => NullTransformer::class,
			]
		);

		$mockTransformer = $this->getMockBuilder( WebPTransformer::class )->disableOriginalConstructor()->getMock();
		$mockTransformer->expects( $this->once() )->method( 'transform' )->willThrowException( new Exception() );

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'exists' )->willReturn( true );

		$repo = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repo->expects( $this->once() )
			->method( 'findFile' )
			->willReturn( $fileMock );

		$factoryMock = $this->getMockBuilder( TransformerFactory::class )->getMock();
		$factoryMock->expects( $this->once() )
			->method( 'getInstance' )
			->willReturn( $mockTransformer );

		$this->setService( 'RepoGroup', $repo );
		$this->setService( 'WebPTransformerFactory', $factoryMock );

		$this->assertFalse( $job->run() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\TransformImageJob::run
	 * @return void
	 * @throws Exception
	 */
	public function testRunTransformLikeThumb() {
		NullTransformer::$transformStatus = Status::newGood();
		NullTransformer::$canTransform = true;

		$job = new TransformImageJob(
			null,
			[
				'title' => 'Foo',
				'transformer' => NullTransformer::class,
				'width' => 400,
			]
		);

		$mockTransformer = $this->getMockBuilder( WebPTransformer::class )->disableOriginalConstructor()->getMock();
		$mockTransformer->expects( $this->once() )->method( 'transformLikeThumb' )->willReturn( Status::newGood() );

		$fileMock = $this->getMockBuilder( LocalFile::class )->disableOriginalConstructor()->getMock();
		$fileMock->expects( $this->once() )->method( 'exists' )->willReturn( true );

		$repo = $this->getMockBuilder( RepoGroup::class )->disableOriginalConstructor()->getMock();
		$repo->expects( $this->once() )
			->method( 'findFile' )
			->willReturn( $fileMock );

		$factoryMock = $this->getMockBuilder( TransformerFactory::class )->getMock();
		$factoryMock->expects( $this->once() )
			->method( 'getInstance' )
			->willReturn( $mockTransformer );

		$this->setService( 'RepoGroup', $repo );
		$this->setService( 'WebPTransformerFactory', $factoryMock );

		$this->assertTrue( $job->run() );
	}

}
