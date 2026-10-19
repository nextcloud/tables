<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\ColumnService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\ViewService;
use OCA\Tables\Validation\ColumnDtoValidator;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ColumnServiceAuditLogTest extends TestCase {
	private ColumnMapper&MockObject $mapper;
	private AuditLogServiceInterface&MockObject $auditLogService;
	private ColumnService $service;

	protected function setUp(): void {
		parent::setUp();

		$permissionsService = $this->createMock(PermissionsService::class);
		$permissionsService->method('canDeleteColumnsByTableId')->willReturn(true);
		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('find')->willReturn(new Table());
		$this->mapper = $this->createMock(ColumnMapper::class);
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$this->service = new ColumnService(
			$permissionsService,
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->mapper,
			$tableMapper,
			$this->createMock(ViewService::class),
			$this->createMock(RowService::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(NotificationHelper::class),
			$this->createMock(IL10N::class),
			$this->createMock(UserHelper::class),
			$this->createMock(ColumnDtoValidator::class),
			$this->auditLogService,
		);

		$column = new Column();
		$column->setId(9);
		$column->setTableId(5);
		$column->setType('text');
		$column->setCreatedBy('alice');
		$column->setLastEditBy('alice');
		$this->mapper->method('find')->with(9)->willReturn($column);
	}

	public function testDeleteLogsCriticalAction(): void {
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ColumnDeleted, 'Column with ID: %s of table with ID: %s was deleted', [
				'columnId' => 9,
				'tableId' => 5,
			]);

		$this->service->delete(9, false, 'alice');
	}

	public function testDeleteTogetherWithTableDoesNotLog(): void {
		$this->auditLogService->expects($this->never())->method('log');

		$this->service->delete(9, true, 'alice');
	}
}
