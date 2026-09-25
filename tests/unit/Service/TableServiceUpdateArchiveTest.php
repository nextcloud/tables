<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\Permissions;
use OCA\Tables\Service\ArchiveService;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\TableTemplateService;
use OCA\Tables\Service\ViewService;
use OCP\App\IAppManager;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Covers the per-user semantics of the legacy `archived` flag on
 * TableService::update() / applyArchivedOnUpdate(): an owner change is a shared
 * change, a non-owner override stays private and must not surface as a shared
 * edit (persisted update, federation notice, activity entry).
 */
class TableServiceUpdateArchiveTest extends TestCase {
	private PermissionsService&MockObject $permissionsService;
	private TableMapper&MockObject $mapper;
	private ArchiveService&MockObject $archiveService;
	private FederationService&MockObject $federationService;
	private ActivityManager&MockObject $activityManager;
	private Table $table;
	private TableService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->mapper = $this->createMock(TableMapper::class);
		$this->archiveService = $this->createMock(ArchiveService::class);
		$this->federationService = $this->createMock(FederationService::class);
		$this->activityManager = $this->createMock(ActivityManager::class);

		$columnService = $this->createMock(ColumnService::class);
		$rowService = $this->createMock(RowService::class);
		$viewService = $this->createMock(ViewService::class);
		$shareService = $this->createMock(ShareService::class);
		$userHelper = $this->createMock(UserHelper::class);
		$favoritesService = $this->createMock(FavoritesService::class);

		$this->permissionsService->method('preCheckUserId')->willReturnArgument(0);
		$this->permissionsService->method('canUpdateTable')->willReturn(true);
		$shareService->method('findAll')->willReturn([]);
		$shareService->method('getSharedPermissionsIfSharedWithMe')->willReturn(new Permissions(read: true));
		$rowService->method('getRowsCount')->willReturn(0);
		$columnService->method('getColumnsCount')->willReturn(0);
		$viewService->method('findAll')->willReturn([]);
		$userHelper->method('getUserDisplayName')->willReturn('');
		$favoritesService->method('isFavorite')->willReturn(false);

		$this->table = new Table();
		$this->table->setId(5);
		$this->table->setTitle('Test');
		$this->mapper->method('find')->willReturn($this->table);

		$this->service = new TableService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			null,
			$this->mapper,
			$this->createMock(TableTemplateService::class),
			$columnService,
			$rowService,
			$viewService,
			$shareService,
			$userHelper,
			$favoritesService,
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ContextService::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IL10N::class),
			$this->createMock(Defaults::class),
			$this->activityManager,
			$this->federationService,
			$this->archiveService,
		);
	}

	public function testOwnerArchivingFlipsSharedFlagAndAnnouncesChange(): void {
		$this->table->setOwnership('alice');
		$this->table->setArchived(false);

		$this->archiveService->expects($this->once())
			->method('archiveForUser')
			->with('alice', Application::NODE_TYPE_TABLE, 5, true, false);
		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->federationService->expects($this->once())->method('notifyNodeUpdate');
		$this->activityManager->expects($this->once())->method('triggerUpdateEvents');

		$result = $this->service->update(5, null, null, null, true, 'alice');
		$this->assertTrue($result->isArchived());
	}

	public function testNonOwnerArchivingStaysPrivate(): void {
		$this->table->setOwnership('alice');
		$this->table->setArchived(false);

		$this->archiveService->expects($this->once())
			->method('archiveForUser')
			->with('bob', Application::NODE_TYPE_TABLE, 5, false, false);
		$this->mapper->expects($this->never())->method('update');
		$this->federationService->expects($this->never())->method('notifyNodeUpdate');
		$this->activityManager->expects($this->never())->method('triggerUpdateEvents');

		$this->service->update(5, null, null, null, true, 'bob');
	}

	public function testNonOwnerUnarchivingStaysPrivate(): void {
		$this->table->setOwnership('alice');
		$this->table->setArchived(true);

		$this->archiveService->expects($this->once())
			->method('unarchiveForUser')
			->with('bob', Application::NODE_TYPE_TABLE, 5, false, true);
		$this->mapper->expects($this->never())->method('update');
		$this->activityManager->expects($this->never())->method('triggerUpdateEvents');

		$this->service->update(5, null, null, null, false, 'bob');
	}

	public function testOwnerRepeatingCurrentStateIsNotAnnounced(): void {
		$this->table->setOwnership('alice');
		$this->table->setArchived(true);

		$this->archiveService->expects($this->once())
			->method('archiveForUser')
			->with('alice', Application::NODE_TYPE_TABLE, 5, true, true);
		$this->mapper->expects($this->never())->method('update');
		$this->activityManager->expects($this->never())->method('triggerUpdateEvents');

		$this->service->update(5, null, null, null, true, 'alice');
	}

	public function testNonOwnerArchivingAlongsideSharedFieldStillAnnouncesTheSharedField(): void {
		$this->table->setOwnership('alice');
		$this->table->setArchived(false);

		$this->archiveService->expects($this->once())
			->method('archiveForUser')
			->with('bob', Application::NODE_TYPE_TABLE, 5, false, false);
		// the title change is a shared change, so the update must still be persisted and announced
		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->activityManager->expects($this->once())->method('triggerUpdateEvents');

		$this->service->update(5, 'New title', null, null, true, 'bob');
	}
}
