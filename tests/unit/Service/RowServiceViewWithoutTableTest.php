<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A view without a table has no rows, so changing or deleting a row through it is a 404, not a 500.
 */
class RowServiceViewWithoutTableTest extends TestCase {
	private RowService $service;

	protected function setUp(): void {
		parent::setUp();

		$row2Mapper = $this->createMock(Row2Mapper::class);
		$row2Mapper->method('getTableIdForRow')->willReturn(4);
		$row2Mapper->method('find')->willReturn($this->createMock(Row2::class));
		$row2Mapper->expects($this->never())->method('isRowInViewPresent');

		$permissionsService = $this->createMock(PermissionsService::class);
		$permissionsService->method('canReadRowsByElementId')->willReturn(true);
		$permissionsService->method('canUpdateRowsByViewId')->willReturn(true);
		$permissionsService->method('canDeleteRowsByViewId')->willReturn(true);

		$view = new View();
		$view->setId(6);
		$view->setTableId(null);
		$view->setType(View::TYPE_GRID);
		$viewMapper = $this->createMock(ViewMapper::class);
		$viewMapper->method('find')->with(6)->willReturn($view);

		$this->service = new RowService(
			$this->createMock(LoggerInterface::class),
			'alice',
			$permissionsService,
			$this->createMock(ColumnMapper::class),
			$viewMapper,
			$this->createMock(TableMapper::class),
			$row2Mapper,
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ColumnsHelper::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(NotificationHelper::class),
			$this->createMock(IDBConnection::class),
		);
	}

	public function testUpdatingARowThroughAViewWithoutATableIsNotFound(): void {
		$this->expectException(NotFoundError::class);
		$this->expectExceptionMessage('has no rows to update');
		$this->service->updateSet(12, 6, [], 'alice', null);
	}

	public function testDeletingARowThroughAViewWithoutATableIsNotFound(): void {
		$this->expectException(NotFoundError::class);
		$this->expectExceptionMessage('has no rows to delete');
		$this->service->delete(12, 6, 'alice');
	}
}
