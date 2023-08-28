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
use JobQueueGroup;
use MediaWiki\Extension\WebP\Hooks\MainHooks;
use MediaWiki\Extension\WebP\Transformer\AvifTransformer;
use MediaWiki\Extension\WebP\Transformer\WebPTransformer;
use MediaWiki\Title\Title;
use RuntimeException;
use UploadBase;

/**
 * @group WebP
 */
class MainHooksTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks
	 * @return void
	 * @throws Exception
	 */
	public function testConstructor() {
		$hooks = new MainHooks(
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getJobQueueGroup()
		);

		$this->assertInstanceOf( MainHooks::class, $hooks );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks::setup
	 * @return void
	 * @throws Exception
	 */
	public function testSetupDisabledHashUpload() {
		$this->setMwGlobals( [
			'wgHashedUploadDirectory' => false,
		] );
		$this->expectException( RuntimeException::class );

		MainHooks::setup();
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks::setup
	 * @return void
	 * @throws Exception
	 */
	public function testSetupEmptyTransformer() {
		$this->setMwGlobals( [
			'wgEnabledTransformers' => [],
		] );

		MainHooks::setup();

		global $wgEnabledTransformers;

		$this->assertEquals( [ WebPTransformer::class ], $wgEnabledTransformers );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks::setup
	 * @return void
	 * @throws Exception
	 */
	public function testSetupSortTransformer() {
		$this->setMwGlobals( [
			'wgEnabledTransformers' => [
				WebPTransformer::class,
				AvifTransformer::class
			],
		] );

		MainHooks::setup();

		global $wgEnabledTransformers;

		$this->assertEquals( AvifTransformer::class, $wgEnabledTransformers[0] );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks::onUploadComplete
	 * @return void
	 * @throws Exception
	 */
	public function testOnUploadCompleteNotEnabled() {
		$this->overrideConfigValues( [
			'WebPEnableConvertOnUpload' => false,
		] );

		$queue = $this->getMockBuilder( JobQueueGroup::class )->disableOriginalConstructor()->getMock();
		$queue->expects( $this->never() )->method( 'push' );

		$upload = $this->getMockBuilder( UploadBase::class )->getMock();

		$hooks = new MainHooks( $this->getServiceContainer()->getMainConfig(), $queue );

		$hooks->onUploadComplete( $upload );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks::onUploadComplete
	 * @return void
	 * @throws Exception
	 */
	public function testOnUploadCompleteFileNull() {
		$this->overrideConfigValues( [
			'WebPEnableConvertOnUpload' => true,
		] );

		$queue = $this->getMockBuilder( JobQueueGroup::class )->disableOriginalConstructor()->getMock();
		$queue->expects( $this->never() )->method( 'push' );

		$upload = $this->getMockBuilder( UploadBase::class )->getMock();
		$upload->expects( $this->once() )->method( 'getLocalFile' )->willReturn( null );

		$hooks = new MainHooks( $this->getServiceContainer()->getMainConfig(), $queue );

		$hooks->onUploadComplete( $upload );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks::onUploadComplete
	 * @return void
	 * @throws Exception
	 */
	public function testOnUploadComplete() {
		$this->overrideConfigValues( [
			'WebPEnableConvertOnUpload' => true,
			'EnabledTransformers' => [ WebPTransformer::class ],
		] );

		$queue = $this->getMockBuilder( JobQueueGroup::class )->disableOriginalConstructor()->getMock();
		$queue->expects( $this->once() )->method( 'push' );

		$upload = $this->getMockBuilder( UploadBase::class )->getMock();
		$upload->expects( $this->once() )->method( 'getLocalFile' )->willReturn( 'file' );

		$hooks = new MainHooks( $this->getServiceContainer()->getMainConfig(), $queue );

		$hooks->onUploadComplete( $upload );
	}

	/**
	 * @covers \MediaWiki\Extension\WebP\Hooks\MainHooks::onFileUndeleteComplete
	 * @return void
	 * @throws Exception
	 */
	public function testOnFileUndeleteComplete() {
		$this->overrideConfigValues( [
			'WebPEnableConvertOnUpload' => true,
			'EnabledTransformers' => [ WebPTransformer::class ],
		] );

		$queue = $this->getMockBuilder( JobQueueGroup::class )->disableOriginalConstructor()->getMock();
		$queue->expects( $this->once() )->method( 'push' );

		$hooks = new MainHooks( $this->getServiceContainer()->getMainConfig(), $queue );

		$hooks->onFileUndeleteComplete(
			Title::newFromText( 'Foo' ),
			[],
			$this->getServiceContainer()->getUserFactory()->newAnonymous(),
			''
		);
	}
}
