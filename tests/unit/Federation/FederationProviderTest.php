<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Federation;

use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\FederationDisabledError;
use OCA\Tables\Federation\FederationProvider;
use OCA\Tables\Service\FederationService;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use PHPUnit\Framework\TestCase;

class FederationProviderTest extends TestCase {
	private TableMapper $tableMapper;
	private ViewMapper $viewMapper;
	private ICloudIdManager $cloudIdManager;
	private ShareMapper $shareMapper;
	private FederationProvider $provider;
	private FederationService $federationService;

	protected function setUp(): void {
		parent::setUp();
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->federationService = $this->createMock(FederationService::class);

		$this->provider = new FederationProvider(
			$this->tableMapper,
			$this->viewMapper,
			$this->shareMapper,
			$this->cloudIdManager,
			$this->federationService,
		);
	}

	public function testShareReceivedThrowsWhenIncomingFederationDisabled(): void {
		$share = $this->createMock(ICloudFederationShare::class);
		$share->method('getShareWith')->willReturn('admin');
		$share->method('getDescription')->willReturn(json_encode(['nodeType' => 'table']));

		$this->federationService->expects($this->once())
			->method('ensureIncomingFederationEnabled')
			->willThrowException(new FederationDisabledError('Federation is disabled'));

		$this->expectException(ProviderCouldNotAddShareException::class);
		$this->provider->shareReceived($share);
	}

	public function testShareReceivedCreatesTable(): void {
		$share = $this->createMock(ICloudFederationShare::class);
		$share->method('getShareWith')->willReturn('admin');
		$share->method('getResourceName')->willReturn('Federation Table');
		$share->method('getProviderId')->willReturn('2');
		$share->method('getOwner')->willReturn('admin@nextcloud.local');
		$share->method('getShareSecret')->willReturn('secret123');
		$share->method('getSharedBy')->willReturn('admin@nextcloud.local');
		$share->method('getDescription')->willReturn(json_encode(['emoji' => '🚀', 'nodeType' => 'table']));

		$this->tableMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Table $table) {
				$table->setId(1);
				$this->assertEquals('Federation Table', $table->getTitle());
				$this->assertEquals(2, $table->getExternalId());
				$this->assertEquals('admin@nextcloud.local', $table->getOwnership());
				$this->assertEquals('🚀', $table->getEmoji());
				return $table;
			});

		$this->shareMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Share $share) {
				$this->assertEquals('table', $share->getNodeType());
				$this->assertEquals(ShareReceiverType::USER, $share->getReceiverType());
				$this->assertEquals('admin', $share->getReceiver());
				return $share;
			});

		$result = $this->provider->shareReceived($share);
		$this->assertEquals('1', $result);
	}

	public function testShareReceivedCreatesView(): void {
		$share = $this->createMock(ICloudFederationShare::class);
		$share->method('getShareWith')->willReturn('admin');
		$share->method('getResourceName')->willReturn('Federation View');
		$share->method('getProviderId')->willReturn('5');
		$share->method('getOwner')->willReturn('admin@nextcloud.local');
		$share->method('getShareSecret')->willReturn('secret456');
		$share->method('getSharedBy')->willReturn('admin@nextcloud.local');
		$share->method('getDescription')->willReturn(json_encode(['emoji' => '🦆', 'nodeType' => 'view']));

		$this->viewMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (View $view) {
				$view->setId(99);
				$this->assertEquals('Federation View', $view->getTitle());
				$this->assertEquals(5, $view->getExternalId());
				$this->assertEquals('🦆', $view->getEmoji());
				return $view;
			});

		$this->shareMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Share $share) {
				$this->assertEquals('view', $share->getNodeType());
				return $share;
			});

		$result = $this->provider->shareReceived($share);
		$this->assertEquals('99', $result);
	}

	public function testNotificationReceivedPermissionUpdate(): void {
		$share = new Share();
		$share->setNodeId(1);
		$share->setToken('abcdefghijklmnop');

		$this->shareMapper->method('findByToken')->willReturn($share);
		$this->shareMapper->expects($this->once())->method('update');

		$this->provider->notificationReceived(
			FederationProvider::NOTIFICATION_UPDATE_PERMISSIONS,
			'1',
			[
				'sharedSecret' => 'abcdefghijklmnop',
				'permissionRead' => true,
				'permissionCreate' => false,
				'permissionUpdate' => true,
				'permissionDelete' => false,
			]
		);

		$this->assertTrue($share->getPermissionRead());
		$this->assertFalse($share->getPermissionCreate());
		$this->assertTrue($share->getPermissionUpdate());
		$this->assertFalse($share->getPermissionDelete());
	}

	public function testNotificationReceivedNodeUpdate(): void {
		$table = new Table();
		$table->setId(1);
		$table->setExternalId(2);
		$table->setShareToken('abcdefghijklmnop');
		$table->setTitle('Old Title');
		$table->setEmoji('🐶');

		$this->tableMapper->method('findByExternalIdAndToken')->willReturn($table);
		$this->tableMapper->expects($this->once())->method('update')->with($table);

		$this->provider->notificationReceived(
			FederationProvider::NOTIFICATION_UPDATE_NODE,
			'2',
			[
				'sharedSecret' => 'abcdefghijklmnop',
				'nodeType' => 'table',
				'title' => 'New Title',
				'emoji' => '🦆',
			]
		);

		$this->assertEquals('New Title', $table->getTitle());
		$this->assertEquals('🦆', $table->getEmoji());
	}

	public function testNotificationReceiveNodeDelete(): void {
		$table = new Table();
		$table->setId(1);
		$table->setExternalId(2);
		$table->setShareToken('abcdefghijklmnop');

		$this->tableMapper->method('findByExternalIdAndToken')->willReturn($table);
		$this->shareMapper->expects($this->once())->method('deleteByNode')->with(1, 'table');
		$this->tableMapper->expects($this->once())->method('delete')->with($table);

		$this->provider->notificationReceived(
			FederationProvider::NOTIFICATION_DELETE_NODE,
			'2',
			[
				'sharedSecret' => 'abcdefghijklmnop',
				'nodeType' => 'table',
			]
		);
	}
}
