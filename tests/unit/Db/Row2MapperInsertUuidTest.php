<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use Psr\Log\LoggerInterface;

class Row2MapperInsertUuidTest extends DatabaseTestCase {
	private Row2Mapper $mapper;
	private RowSleeveMapper $rowSleeveMapper;
	private int $tableId;

	protected function setUp(): void {
		parent::setUp();
		$logger = $this->createMock(LoggerInterface::class);
		$userHelper = $this->createMock(UserHelper::class);
		$this->rowSleeveMapper = new RowSleeveMapper($this->connectionAdapter, $logger);
		$this->mapper = new Row2Mapper(
			'user1',
			$this->connectionAdapter,
			$logger,
			$userHelper,
			$this->rowSleeveMapper,
			new ColumnsHelper($userHelper, $this->createMock(CircleHelper::class)),
			$this->createMock(ColumnMapper::class),
		);
		$this->tableId = $this->createTestTable()['id'];
	}

	protected function tearDown(): void {
		$this->rowSleeveMapper->deleteAllForTable($this->tableId);
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('tables_tables')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($this->tableId)))
			->executeStatement();
		parent::tearDown();
	}

	public function testANewRowGetsAUuid(): void {
		$row = new Row2();
		$row->setTableId($this->tableId);

		$inserted = $this->mapper->insert($row);

		$this->assertTrue(Uuid::isValid((string)$inserted->getUuid()));
		$this->assertSame($inserted->getUuid(), $this->rowSleeveMapper->find($inserted->getId())->getUuid());
	}

	public function testARowWithExistingDataKeepsItsUuid(): void {
		$uuid = Uuid::v7()->toRfc4122();
		$row = $this->rowWithExistingData();
		$row->setUuid($uuid);

		$inserted = $this->mapper->insert($row);

		$this->assertSame($uuid, $this->rowSleeveMapper->find($inserted->getId())->getUuid());
	}

	public function testARowWithExistingDataWithoutUuidGetsOne(): void {
		$inserted = $this->mapper->insert($this->rowWithExistingData());

		$this->assertTrue(Uuid::isValid((string)$inserted->getUuid()));
		$this->assertSame($inserted->getUuid(), $this->rowSleeveMapper->find($inserted->getId())->getUuid());
	}

	private function rowWithExistingData(): Row2 {
		$row = new Row2();
		$row->setId($this->unusedRowId());
		$row->setTableId($this->tableId);
		$row->setCreatedBy('user1');
		$row->setCreatedAt('2026-10-14 10:00:00');
		$row->setLastEditBy('user1');
		$row->setLastEditAt('2026-10-14 10:00:00');
		return $row;
	}

	private function unusedRowId(): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->select($qb->func()->max('id'))->from('tables_row_sleeves');
		return (int)$qb->executeQuery()->fetchOne() + 1000;
	}
}
