<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Tests\Transformer;

use File;
use MediaWiki\Extension\WebP\Transformer\WebPTransformer;
use RuntimeException;

/**
 * @group WebP
 */
class WebPTransformerTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\WebPTransformer
	 * @return void
	 */
	public function testConstructor() {
		$file = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$file->expects( $this->once() )->method( 'getMimeType' )->willReturn( 'image/jpg' );

		$this->assertInstanceOf( WebPTransformer::class, new WebPTransformer( $file, [] ) );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\WebPTransformer
	 * @return void
	 */
	public function testConstructorInvalidMime() {
		$this->expectException( RuntimeException::class );

		$file = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$file->expects( $this->exactly( 2 ) )->method( 'getMimeType' )->willReturn( 'image/gif' );

		new WebPTransformer( $file, [] );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\WebPTransformer::getFileExtension
	 * @return void
	 */
	public function testGetExtension() {
		$this->assertEquals( 'webp', WebPTransformer::getFileExtension() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\WebPTransformer::getMimeType
	 * @return void
	 */
	public function testGetMime() {
		$this->assertEquals( 'image/webp', WebPTransformer::getMimeType() );
	}
}
