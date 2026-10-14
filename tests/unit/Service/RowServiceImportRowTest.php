<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Notification\NotificationHelper;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class RowServiceImportRowTest extends DatabaseTestCase {
	private Row2Mapper&MockObject $row2Mapper;
	private RowService $rowService;
	private Table $table;

	protected function setUp(): void {
		parent::setUp();
		$this->row2Mapper = $this->createMock(Row2Mapper::class);
		$this->rowService = new RowService(
			$this->createMock(LoggerInterface::class),
			'user1',
			$this->createMock(PermissionsService::class),
			$this->createMock(ColumnMapper::class),
			$this->createMock(ViewMapper::class),
			$this->createMock(TableMapper::class),
			$this->row2Mapper,
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ColumnsHelper::class),
			$this->createMock(ActivityManager::class),
			$this->createMock(NotificationHelper::class),
			$this->connectionAdapter,
		);
		$this->table = new Table();
		$this->table->setId($this->createTestTable()['id']);
		$this->table->setOwnership('user1');
	}

	protected function tearDown(): void {
		foreach (['tables_row_sleeves' => 'table_id', 'tables_tables' => 'id'] as $tableName => $column) {
			$qb = $this->connection->getQueryBuilder();
			$qb->delete($tableName)
				->where($qb->expr()->eq($column, $qb->createNamedParameter($this->table->getId())))
				->executeStatement();
		}
		parent::tearDown();
	}

	private function importRow(array $exportedRow): ?string {
		$rowId = $this->rowService->importRow($this->table, $exportedRow + [
			'createdAt' => '2026-10-14 10:00:00',
			'lastEditAt' => '2026-10-14 10:00:00',
		]);
		$qb = $this->connection->getQueryBuilder();
		$qb->select('uuid')
			->from('tables_row_sleeves')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($rowId)));
		$uuid = $qb->executeQuery()->fetchOne();
		return $uuid === false ? null : $uuid;
	}

	public function testKeepsTheExportedUuidWhenItIsUnused(): void {
		$exportedUuid = Uuid::v7()->toRfc4122();
		$this->row2Mapper->method('findIdByUuid')->with($exportedUuid)->willReturn(null);

		$this->assertSame($exportedUuid, $this->importRow(['uuid' => $exportedUuid]));
	}

	public function testGeneratesANewUuidWhenTheExportedOneIsUsed(): void {
		$exportedUuid = Uuid::v7()->toRfc4122();
		$this->row2Mapper->method('findIdByUuid')->with($exportedUuid)->willReturn(99);

		$uuid = $this->importRow(['uuid' => $exportedUuid]);

		$this->assertNotSame($exportedUuid, $uuid);
		$this->assertTrue(Uuid::isValid($uuid));
	}

	public function testGeneratesAUuidWhenTheExportHasNone(): void {
		$this->row2Mapper->expects($this->never())->method('findIdByUuid');

		$this->assertTrue(Uuid::isValid($this->importRow([])));
	}

	public function testGeneratesAUuidWhenTheExportedOneIsInvalid(): void {
		$this->row2Mapper->expects($this->never())->method('findIdByUuid');

		$uuid = $this->importRow(['uuid' => 'not-a-uuid']);

		$this->assertTrue(Uuid::isValid($uuid));
	}
}
