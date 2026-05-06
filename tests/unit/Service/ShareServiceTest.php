<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\ContextNavigationMapper;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ValueObject\ShareCreate;
use OCP\IDBConnection;
use OCP\Security\IHasher;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ShareServiceTest extends TestCase {
	private $permissionsService;
	private $logger;
	private $mapper;
	private $tableMapper;
	private $viewMapper;
	private $userHelper;
	private $groupHelper;
	private $circleHelper;
	private $contextNavigationMapper;
	private $dbc;
	private $shareManager;
	private $hasher;
	protected $shareService;

	protected function setUp(): void {
		parent::setUp();
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->mapper = $this->createMock(ShareMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->userHelper = $this->createMock(UserHelper::class);
		$this->groupHelper = $this->createMock(GroupHelper::class);
		$this->circleHelper = $this->createMock(CircleHelper::class);
		$this->contextNavigationMapper = $this->createMock(ContextNavigationMapper::class);
		$this->dbc = $this->createMock(IDBConnection::class);
		$this->shareManager = $this->createMock(IShareManager::class);
		$this->hasher = $this->createMock(IHasher::class);

		$this->shareService = new ShareService(
			$this->permissionsService,
			$this->logger,
			'admin',
			$this->mapper,
			$this->tableMapper,
			$this->viewMapper,
			$this->userHelper,
			$this->groupHelper,
			$this->circleHelper,
			$this->contextNavigationMapper,
			$this->dbc,
			$this->shareManager,
			$this->hasher,
		);
	}

	public function testCreateContextShareSetsAllPermissionsFalse(): void {
		$this->mapper->method('insert')->willReturnCallback(function (Share $share) {
			return $share;
		});

		$share = $this->shareService->create(
			new ShareCreate(
				1,
				'context',
				'user1',
				'user',
				true, true, true, true, true,
				0
			)
		);

		$this->assertFalse($share->getPermissionRead());
		$this->assertFalse($share->getPermissionCreate());
		$this->assertFalse($share->getPermissionUpdate());
		$this->assertFalse($share->getPermissionDelete());
		$this->assertFalse($share->getPermissionManage());
	}

	public function testUpdatePermissionThrowsOnContextShare(): void {
		$share = new Share();
		$share->setNodeType('context');
		$share->setNodeId(9);
		$this->mapper->method('find')->willReturn($share);

		$this->expectException(PermissionError::class);
		$this->shareService->updatePermission(1, 'manage', true);
	}
}
