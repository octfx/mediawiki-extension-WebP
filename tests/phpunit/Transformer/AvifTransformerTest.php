<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Tests\Transformer;

use File;
use MediaWiki\Extension\WebP\Transformer\AvifTransformer;
use RuntimeException;

/**
 * @group WebP
 */
class AvifTransformerTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\AvifTransformer
	 * @return void
	 */
	public function testConstructor() {
		$file = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$file->expects( $this->once() )->method( 'getMimeType' )->willReturn( 'image/jpg' );

		$this->assertInstanceOf( AvifTransformer::class, new AvifTransformer( $file, [] ) );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\AvifTransformer
	 * @return void
	 */
	public function testConstructorInvalidMime() {
		$this->expectException( RuntimeException::class );

		$file = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$file->expects( $this->exactly( 2 ) )->method( 'getMimeType' )->willReturn( 'image/gif' );

		new AvifTransformer( $file, [] );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\AvifTransformer::getFileExtension
	 * @return void
	 */
	public function testGetExtension() {
		$this->assertEquals( 'avif', AvifTransformer::getFileExtension() );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\AvifTransformer::getMimeType
	 * @return void
	 */
	public function testGetMime() {
		$this->assertEquals( 'image/avif', AvifTransformer::getMimeType() );
	}
}
