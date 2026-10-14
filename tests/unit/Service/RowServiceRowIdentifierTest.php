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
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
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

class RowServiceRowIdentifierTest extends TestCase {
	private const UUID = '0199e3a0-1f2b-7c3d-8e4f-5a6b7c8d9e0f';

	private PermissionsService&MockObject $permissionsService;
	private ColumnMapper&MockObject $columnMapper;
	private ViewMapper&MockObject $viewMapper;
	private Row2Mapper&MockObject $row2Mapper;
	private RowService $rowService;

	protected function setUp(): void {
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->row2Mapper = $this->createMock(Row2Mapper::class);
		$this->rowService = new RowService(
			$this->createMock(LoggerInterface::class),
			'user1',
			$this->permissionsService,
			$this->columnMapper,
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

	private function view(int $viewId, int $tableId): View {
		$view = new View();
		$view->setId($viewId);
		$view->setTableId($tableId);
		return $view;
	}

	private function row(int $rowId): Row2 {
		$row = new Row2();
		$row->setId($rowId);
		$row->setUuid(self::UUID);
		return $row;
	}

	public function testResolveRowIdAcceptsAnIntegerId(): void {
		$this->row2Mapper->expects($this->never())->method('findIdByUuid');

		$this->assertSame(42, $this->rowService->resolveRowId('42', 3, null));
	}

	public function testResolveRowIdLooksUpAUuidWithinTheTable(): void {
		$this->row2Mapper->expects($this->once())
			->method('findIdByUuid')
			->with(self::UUID, 3)
			->willReturn(7);

		$this->assertSame(7, $this->rowService->resolveRowId(strtoupper(self::UUID), 3, null));
	}

	public function testResolveRowIdLooksUpAUuidWithinTheTableOfTheView(): void {
		$this->viewMapper->method('find')->with(5)->willReturn($this->view(5, 3));
		$this->row2Mapper->expects($this->once())
			->method('findIdByUuid')
			->with(self::UUID, 3)
			->willReturn(7);

		$this->assertSame(7, $this->rowService->resolveRowId(self::UUID, null, 5));
	}

	public function testResolveRowIdThrowsNotFoundForAUuidOutsideTheTable(): void {
		$this->row2Mapper->method('findIdByUuid')->willReturn(null);

		$this->expectException(NotFoundError::class);
		$this->rowService->resolveRowId(self::UUID, 3, null);
	}

	public function testResolveRowIdThrowsNotFoundForAnUnknownView(): void {
		$this->viewMapper->method('find')->willThrowException(new DoesNotExistException('missing'));

		$this->expectException(NotFoundError::class);
		$this->rowService->resolveRowId(self::UUID, null, 5);
	}

	public function testResolveRowIdRejectsAnythingElse(): void {
		$this->expectException(BadRequestError::class);
		$this->rowService->resolveRowId('not-a-row', 3, null);
	}

	public function testResolveRowIdRejectsANegativeId(): void {
		$this->expectException(BadRequestError::class);
		$this->rowService->resolveRowId('-1', 3, null);
	}

	public function testFindInNodeReturnsARowOfTheTable(): void {
		$this->row2Mapper->method('getTableIdForRow')->with(7)->willReturn(3);
		$this->permissionsService->method('canReadRowsByElementId')->with(3, 'table', 'user1')->willReturn(true);
		$this->columnMapper->method('findAllByTable')->with(3)->willReturn([]);
		$this->row2Mapper->method('find')->with(7, [])->willReturn($this->row(7));

		$row = $this->rowService->findInNode(7, 3, null, 'user1');

		$this->assertSame(self::UUID, $row->getUuid());
	}

	public function testFindInNodeDoesNotFindARowOfAnotherTable(): void {
		$this->row2Mapper->method('getTableIdForRow')->with(7)->willReturn(4);
		$this->permissionsService->method('canReadRowsByElementId')->willReturn(true);
		$this->row2Mapper->expects($this->never())->method('find');

		$this->expectException(NotFoundError::class);
		$this->rowService->findInNode(7, 3, null, 'user1');
	}

	public function testFindInNodeDoesNotFindAnUnknownRow(): void {
		$this->row2Mapper->method('getTableIdForRow')->willThrowException(new DoesNotExistException('missing'));

		$this->expectException(NotFoundError::class);
		$this->rowService->findInNode(7, 3, null, 'user1');
	}

	public function testFindInNodeDoesNotFindARowWithoutReadPermission(): void {
		$this->row2Mapper->method('getTableIdForRow')->willReturn(3);
		$this->permissionsService->method('canReadRowsByElementId')->willReturn(false);
		$this->row2Mapper->expects($this->never())->method('find');

		$this->expectException(NotFoundError::class);
		$this->rowService->findInNode(7, 3, null, 'user1');
	}

	public function testFindInNodeReturnsARowOfTheView(): void {
		$view = $this->view(5, 3);
		$this->viewMapper->method('find')->with(5)->willReturn($view);
		$this->permissionsService->method('canReadRowsByElementId')->with(5, 'view', 'user1')->willReturn(true);
		$this->row2Mapper->method('isRowInViewPresent')->with(7, $view, 'user1')->willReturn(true);
		$this->columnMapper->method('findAll')->willReturn([]);
		$this->row2Mapper->method('find')->willReturn($this->row(7));

		$row = $this->rowService->findInNode(7, null, 5, 'user1');

		$this->assertSame(7, $row->getId());
	}

	public function testFindInNodeDoesNotFindARowOutsideTheView(): void {
		$this->viewMapper->method('find')->willReturn($this->view(5, 3));
		$this->permissionsService->method('canReadRowsByElementId')->willReturn(true);
		$this->row2Mapper->method('isRowInViewPresent')->willReturn(false);
		$this->row2Mapper->expects($this->never())->method('find');

		$this->expectException(NotFoundError::class);
		$this->rowService->findInNode(7, null, 5, 'user1');
	}
}
