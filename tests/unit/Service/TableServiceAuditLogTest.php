<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
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

final class TableServiceAuditLogTest extends TestCase {
	private PermissionsService&MockObject $permissionsService;
	private TableMapper&MockObject $mapper;
	private AuditLogServiceInterface&MockObject $auditLogService;
	private TableService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('preCheckUserId')->willReturn('alice');
		$this->mapper = $this->createMock(TableMapper::class);
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$this->service = new TableService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->mapper,
			$this->createMock(TableTemplateService::class),
			$this->createMock(ColumnService::class),
			$this->createMock(RowService::class),
			$this->createMock(ViewService::class),
			$this->createMock(ShareService::class),
			$this->createMock(UserHelper::class),
			$this->createMock(FavoritesService::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ContextService::class),
			$this->createMock(IAppManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(IL10N::class),
			$this->createMock(Defaults::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(FederationService::class),
			$this->auditLogService,
		);
	}

	private function createTable(bool $archived): Table {
		$table = new Table();
		$table->setId(5);
		$table->setTitle('Budget');
		$table->setOwnership('alice');
		$table->setArchived($archived);
		return $table;
	}

	public function testCreateLogsCriticalAction(): void {
		$this->mapper->method('insert')->willReturnCallback(function (Table $table): Table {
			$table->setId(5);
			return $table;
		});
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::TableCreated, 'Table with ID: %s was created by user with ID: %s', [
				'tableId' => 5,
				'owner' => 'alice',
				'template' => 'custom',
			]);

		$this->service->create('Budget', 'custom', null, '', 'alice');
	}

	public function testUpdateLogsArchiving(): void {
		$this->mapper->method('find')->with(5)->willReturn($this->createTable(false));
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canUpdateTable')->willReturn(true);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::TableArchived, 'Table with ID: %s was archived', ['tableId' => 5]);

		$this->service->update(5, null, null, null, true, 'alice');
	}

	public function testUpdateLogsUnarchiving(): void {
		$this->mapper->method('find')->with(5)->willReturn($this->createTable(true));
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canUpdateTable')->willReturn(true);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::TableUnarchived, 'Table with ID: %s was unarchived', ['tableId' => 5]);

		$this->service->update(5, null, null, null, false, 'alice');
	}

	public function testUpdateWithoutArchiveChangeDoesNotLog(): void {
		$this->mapper->method('find')->with(5)->willReturn($this->createTable(false));
		$this->mapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canUpdateTable')->willReturn(true);
		$this->auditLogService->expects($this->never())->method('log');

		$this->service->update(5, 'New title', null, null, false, 'alice');
	}
}
