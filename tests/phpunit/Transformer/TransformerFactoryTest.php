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
