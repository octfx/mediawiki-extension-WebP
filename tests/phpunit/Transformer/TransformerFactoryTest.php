<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\WebP\Tests\Transformer;

use File;
use InvalidArgumentException;
use MediaWiki\Extension\WebP\Transformer\AvifTransformer;
use MediaWiki\Extension\WebP\Transformer\TransformerFactory;
use MediaWiki\Extension\WebP\Transformer\WebPTransformer;

/**
 * @group WebP
 */
class TransformerFactoryTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\TransformerFactory::getInstance
	 * @return void
	 */
	public function testInvalidArgs() {
		$this->expectException( InvalidArgumentException::class );
		$fac = new TransformerFactory();
		$fac->getInstance( WebPTransformer::class, [] );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\TransformerFactory::getInstance
	 * @covers \MediaWiki\Extension\WebP\Transformer\WebPTransformer
	 * @return void
	 */
	public function testGetWebP() {
		$fac = new TransformerFactory();
		$file = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$file->expects( $this->exactly( 2 ) )->method( 'getMimeType' )->willReturn( 'image/jpg' );

		$this->assertInstanceOf( WebPTransformer::class, $fac->getInstance( 'webp', [ $file ] ) );
		$this->assertInstanceOf(
			WebPTransformer::class,
			$fac->getInstance( WebPTransformer::class, [ $file ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\TransformerFactory::getInstance
	 * @covers \MediaWiki\Extension\WebP\Transformer\AvifTransformer
	 * @return void
	 */
	public function testGetAvif() {
		$fac = new TransformerFactory();
		$file = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$file->expects( $this->exactly( 2 ) )->method( 'getMimeType' )->willReturn( 'image/jpg' );

		$this->assertInstanceOf( AvifTransformer::class, $fac->getInstance( 'avif', [ $file ] ) );
		$this->assertInstanceOf(
			AvifTransformer::class,
			$fac->getInstance( AvifTransformer::class, [ $file ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Transformer\TransformerFactory::getInstance
	 * @covers \MediaWiki\Extension\WebP\Transformer\AvifTransformer
	 * @return void
	 */
	public function testGetInvalid() {
		$this->expectException( InvalidArgumentException::class );

		$fac = new TransformerFactory();
		$file = $this->getMockBuilder( File::class )->disableOriginalConstructor()->getMock();
		$file->expects( $this->never() )->method( 'getMimeType' );

		$fac->getInstance( 'foo', [ $file ] );
	}
}
