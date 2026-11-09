<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\RowSleeve;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\Exception;
use Psr\Log\NullLogger;

class RowSleeveMapperTest extends DatabaseTestCase {
	private RowSleeveMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new RowSleeveMapper($this->connectionAdapter, new NullLogger());
		$this->connectionAdapter->beginTransaction();
	}

	protected function tearDown(): void {
		$this->connectionAdapter->rollBack();
		parent::tearDown();
	}

	private function sleeve(int $tableId = 1): RowSleeve {
		$sleeve = new RowSleeve();
		$sleeve->setTableId($tableId);
		$sleeve->setCreatedBy('alice');
		$sleeve->setLastEditBy('alice');
		$sleeve->setCreatedAt('2020-01-01 00:00:00');
		$sleeve->setLastEditAt('2020-01-02 00:00:00');
		return $sleeve;
	}

	public function testEveryInsertionGetsStoredUuidAndProjectionIncludesIt(): void {
		$first = $this->mapper->insert($this->sleeve());
		$second = $this->mapper->insert($this->sleeve(2));
		$this->assertNotNull($first->getUuid());
		$this->assertNotSame($first->getUuid(), $second->getUuid());
		$this->assertSame($first->getUuid(), $this->mapper->find($first->getId())->getUuid());
		$this->assertSame($first->getUuid(), $this->mapper->findMultiple([$first->getId()])[0]->getUuid());
		$this->assertSame($first->getId(), $this->mapper->findByUuid($first->getUuid())->getId());
	}

	public function testExplicitLegacyIdAlsoGetsUuid(): void {
		$sleeve = $this->sleeve();
		$sleeve->setId(900001);
		$this->mapper->insert($sleeve);
		$this->assertNotNull($this->mapper->find(900001)->getUuid());
	}

	public function testImportPreservesUnusedIdentityAndCopiesCollisionsAcrossTables(): void {
		$sleeve = $this->sleeve();
		$uuid = '019f90c5-8576-7f0b-9b14-9bf49b2826cf';
		$sleeve->setUuid($uuid);
		$first = $this->mapper->insertImported($sleeve);
		$this->assertSame($uuid, $first->getUuid());
		$sleeve->setTableId(2);
		$copy = $this->mapper->insertImported($sleeve);
		$this->assertNotSame($uuid, $copy->getUuid());
		$this->assertTrue($this->connectionAdapter->inTransaction());
		$this->assertSame($uuid, $this->mapper->find($first->getId())->getUuid());
		$this->assertSame('2020-01-01 00:00:00', $copy->getCreatedAt());
		$this->assertSame($copy->getUuid(), $this->mapper->find($copy->getId())->getUuid());
	}

	public function testUniqueIndexAppliesAcrossTables(): void {
		$first = $this->mapper->insert($this->sleeve());
		$second = $this->sleeve(2);
		$second->setUuid($first->getUuid());
		$this->expectException(Exception::class);
		$this->mapper->insert($second);
	}

	public function testMultipleHistoricalNullsRemainReadable(): void {
		$insert = $this->connectionAdapter->getQueryBuilder();
		$insert->insert('tables_row_sleeves')->values([
			'table_id' => $insert->createNamedParameter(1),
			'created_by' => $insert->createNamedParameter('alice'),
			'created_at' => $insert->createNamedParameter('2020-01-01 00:00:00'),
			'last_edit_by' => $insert->createNamedParameter('alice'),
			'last_edit_at' => $insert->createNamedParameter('2020-01-01 00:00:00'),
		]);
		$insert->executeStatement();
		$first = $insert->getLastInsertId();
		$insert->executeStatement();
		$second = $insert->getLastInsertId();
		$this->assertNull($this->mapper->find($first)->getUuid());
		$this->assertNull($this->mapper->find($second)->getUuid());
	}
}
