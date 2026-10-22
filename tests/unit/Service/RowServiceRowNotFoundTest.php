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
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Changing or deleting a row that is not reachable through the given view or table is a 404, not a 500.
 */
class RowServiceRowNotFoundTest extends TestCase {
	private const ROW_ID = 12;
	private const TABLE_ID = 4;
	private const VIEW_ID = 6;

	private RowService $service;
	private Row2Mapper&MockObject $row2Mapper;
	private ViewMapper&MockObject $viewMapper;
	private PermissionsService&MockObject $permissionsService;

	protected function setUp(): void {
		parent::setUp();

		$row = new Row2();
		$row->setId(self::ROW_ID);
		$row->setTableId(self::TABLE_ID);

		$this->row2Mapper = $this->createMock(Row2Mapper::class);
		$this->row2Mapper->method('getTableIdForRow')->willReturn(self::TABLE_ID);
		$this->row2Mapper->method('find')->willReturn($row);
		$this->row2Mapper->expects($this->never())->method('update');
		$this->row2Mapper->expects($this->never())->method('delete');

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('canReadRowsByElementId')->willReturn(true);
		$this->permissionsService->method('canUpdateRowsByViewId')->willReturn(true);
		$this->permissionsService->method('canUpdateRowsByTableId')->willReturn(true);
		$this->permissionsService->method('canDeleteRowsByTableId')->willReturn(true);

		$this->viewMapper = $this->createMock(ViewMapper::class);

		$this->service = new RowService(
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->permissionsService,
			$this->createMock(ColumnMapper::class),
			$this->viewMapper,
			$this->createMock(TableMapper::class),
			$this->row2Mapper,
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ColumnsHelper::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(NotificationHelper::class),
			$this->createMock(IDBConnection::class),
		);
	}

	private function givenTheViewExists(): void {
		$view = new View();
		$view->setId(self::VIEW_ID);
		$view->setTableId(self::TABLE_ID);
		$this->viewMapper->method('find')->with(self::VIEW_ID)->willReturn($view);
	}

	public function testUpdatingARowThatIsNotInTheViewIsNotFound(): void {
		$this->givenTheViewExists();
		$this->row2Mapper->method('isRowInViewPresent')->willReturn(false);

		$this->expectException(NotFoundError::class);
		$this->service->updateSet(self::ROW_ID, self::VIEW_ID, [], 'alice', null);
	}

	public function testDeletingARowThatIsNotInTheViewIsNotFound(): void {
		$this->givenTheViewExists();
		$this->permissionsService->method('canDeleteRowsByViewId')->willReturn(true);
		$this->row2Mapper->method('isRowInViewPresent')->willReturn(false);

		$this->expectException(NotFoundError::class);
		$this->service->delete(self::ROW_ID, self::VIEW_ID, 'alice');
	}

	public function testDeletingARowThroughAViewThatDoesNotExistIsNotFound(): void {
		$this->permissionsService->method('canDeleteRowsByViewId')->willReturn(true);
		$this->viewMapper->method('find')->willThrowException(new DoesNotExistException('View not found'));

		$this->expectException(NotFoundError::class);
		$this->service->delete(self::ROW_ID, self::VIEW_ID, 'alice');
	}

	public function testUpdatingARowThatIsNotInTheTableIsNotFound(): void {
		$this->expectException(NotFoundError::class);
		$this->service->updateSet(self::ROW_ID, null, [], 'alice', self::TABLE_ID + 1);
	}

	public function testDeletingARowThatIsNotInTheTableIsNotFound(): void {
		$this->expectException(NotFoundError::class);
		$this->service->delete(self::ROW_ID, null, 'alice', self::TABLE_ID + 1);
	}

	public function testDeletingARowThroughAViewWithoutDeletePermissionIsForbidden(): void {
		$this->permissionsService->method('canDeleteRowsByViewId')->willReturn(false);
		$this->row2Mapper->expects($this->never())->method('isRowInViewPresent');

		$this->expectException(PermissionError::class);
		$this->service->delete(self::ROW_ID, self::VIEW_ID, 'alice');
	}
}
