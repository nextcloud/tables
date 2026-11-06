<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\RowSleeve;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Model\RowIdentifier;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RowServiceUuidTest extends TestCase {
	private RowService $service;
	private PermissionsService $permissions;
	private Row2Mapper $rows;
	private ColumnMapper $columns;
	private ViewMapper $views;

	protected function setUp(): void {
		$this->permissions = $this->createMock(PermissionsService::class);
		$this->rows = $this->createMock(Row2Mapper::class);
		$this->columns = $this->createMock(ColumnMapper::class);
		$this->views = $this->createMock(ViewMapper::class);
		$this->service = new RowService(new NullLogger(), 'alice', $this->permissions, $this->columns, $this->views,
			$this->createMock(TableMapper::class), $this->rows, $this->createMock(IEventDispatcher::class),
			$this->createMock(ColumnsHelper::class), $this->createMock(ActivityManager::class), $this->createMock(NotificationHelper::class));
	}

	private function view(): View {
		$view = new View();
		$view->setId(8);
		$view->setTableId(5);
		$view->setColumnsArray([['columnId' => 1, 'order' => 0]]);
		$view->setFilterArray([[['columnId' => -1, 'operator' => 'is-equal', 'value' => '13']]]);
		return $view;
	}

	private function row(): Row2 {
		$row = new Row2();
		$row->setId(13);
		$row->setTableId(5);
		$row->setUuid('019f90c5-8576-7f0b-9b14-9bf49b2826cf');
		$row->setData([['columnId' => 1, 'value' => 'visible'], ['columnId' => 2, 'value' => 'hidden']]);
		return $row;
	}

	public function testViewOnlyAccessUsesViewScopeAndProjectsBothCellRepresentations(): void {
		$view = $this->view();
		$this->permissions->expects($this->once())->method('canReadRowsByElementId')->with(8, 'view', 'alice')->willReturn(true);
		$this->views->method('find')->with(8)->willReturn($view);
		$this->rows->expects($this->once())->method('findScopedId')->with($this->isInstanceOf(RowIdentifier::class), 5, $view->getFilterArray(), 'alice')->willReturn(13);
		$column = new Column();
		$column->setId(1);
		$column->setTechnicalName('visible');
		$this->columns->method('findAll')->with([1])->willReturn([$column]);
		$this->columns->expects($this->never())->method('findAllByTable');
		$original = $this->row();
		$this->rows->method('find')->with(13, [$column])->willReturn($original);
		$result = $this->service->findByIdentifier(RowIdentifier::fromString('13'), Application::NODE_TYPE_VIEW, 8, 'alice');
		$this->assertSame([['columnId' => 1, 'value' => 'visible']], $result->getData());
		$this->assertSame(['visible' => ['columnId' => 1, 'value' => 'visible']], $result->getDataByAlias());
		$this->assertCount(2, $original->getData());
		$this->assertNotSame($original, $result);
		$this->assertSame($original->getUuid(), $result->getUuid());
	}

	public function testMissingRowReadPermissionIsDeniedBeforeLookup(): void {
		$this->permissions->method('canReadRowsByElementId')->willReturn(false);
		$this->rows->expects($this->never())->method('findScopedId');
		$this->expectException(PermissionError::class);
		$this->service->findByIdentifier(RowIdentifier::fromString('13'), Application::NODE_TYPE_TABLE, 5, 'alice');
	}

	public function testResolvingRowIdDoesNotLoadTheRow(): void {
		$view = $this->view();
		$this->permissions->expects($this->once())->method('canReadRowsByElementId')->with(8, 'view', 'alice')->willReturn(true);
		$this->views->method('find')->with(8)->willReturn($view);
		$this->rows->expects($this->once())->method('findScopedId')->with($this->isInstanceOf(RowIdentifier::class), 5, $view->getFilterArray(), 'alice')->willReturn(13);
		$this->rows->expects($this->never())->method('find');
		$this->columns->expects($this->never())->method('findAll');
		$this->assertSame(13, $this->service->resolveRowId(RowIdentifier::fromString('13'), Application::NODE_TYPE_VIEW, 8, 'alice'));
	}

	public function testScopeMismatchIsNotFoundWithoutLoadingCells(): void {
		$this->permissions->method('canReadRowsByElementId')->willReturn(true);
		$this->rows->method('findScopedId')->willReturn(null);
		$this->rows->expects($this->never())->method('find');
		$this->expectException(NotFoundError::class);
		$this->service->findByIdentifier(RowIdentifier::fromString('13'), Application::NODE_TYPE_TABLE, 5, 'alice');
	}

	/** @dataProvider archiveIdentities */
	public function testArchiveIdentityGoesThroughSleeveInsertionBoundary(mixed $uuid, ?string $expected, bool $includeUuid = true): void {
		$table = new Table();
		$table->setId(5);
		$table->setOwnership('alice');
		$this->rows->expects($this->once())->method('importSleeve')->willReturnCallback(function (RowSleeve $sleeve) use ($expected): RowSleeve {
			$this->assertSame($expected, $sleeve->getUuid());
			$this->assertSame(5, $sleeve->getTableId());
			$this->assertSame('alice', $sleeve->getCreatedBy());
			$sleeve->setId(99);
			$sleeve->assignUuid();
			return $sleeve;
		});
		$archive = ['createdAt' => '2020-01-01 00:00:00', 'lastEditAt' => '2020-01-02 00:00:00'];
		if ($includeUuid) {
			$archive['uuid'] = $uuid;
		}
		$this->assertSame(99, $this->service->importRow($table, $archive));
	}

	public static function archiveIdentities(): array {
		return [[null, null, false], [null, null], ['019F90C5-8576-7F0B-9B14-9BF49B2826CF', '019f90c5-8576-7f0b-9b14-9bf49b2826cf']];
	}

	/** @dataProvider invalidArchiveIdentities */
	public function testInvalidArchiveIdentityFailsBeforeWriting(mixed $uuid): void {
		$this->rows->expects($this->never())->method('importSleeve');
		$this->expectException(\InvalidArgumentException::class);
		$table = new Table();
		$table->setId(5);
		$table->setOwnership('alice');
		$this->service->importRow($table, ['uuid' => $uuid]);
	}

	public static function invalidArchiveIdentities(): array {
		return [[3], [[]], [''], ['019f90c5-8576-4f0b-9b14-9bf49b2826cf']];
	}

	/** @dataProvider mutationMethods */
	public function testRowRemovedBeforeMutationIsNotFound(string $method): void {
		$this->rows->method('getTableIdForRow')->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('Deleted row'));
		$this->expectException(NotFoundError::class);
		if ($method === 'updateSet') {
			$this->service->updateSet(13, null, [], 'alice', 5);
		} else {
			$this->service->delete(13, null, 'alice', 5);
		}
	}

	public static function mutationMethods(): array {
		return [['updateSet'], ['delete']];
	}

	public function testPublicFormatterKeepsUuidAndRemovesPrivateMetadata(): void {
		$row = $this->row();
		$result = $this->service->formatRowsForPublicShare([$row])[0];
		$this->assertSame($row->getUuid(), $result['uuid']);
		$this->assertArrayNotHasKey('tableId', $result);
		$this->assertArrayNotHasKey('createdBy', $result);
		$this->assertArrayNotHasKey('lastEditBy', $result);
	}
}
