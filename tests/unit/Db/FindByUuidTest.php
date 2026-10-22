<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

/**
 * Runs against the database: tables and views found by their uuid.
 */
class FindByUuidTest extends DatabaseTestCase {
	private TableMapper $tableMapper;
	private ViewMapper $viewMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanupTablesData();
		$userHelper = $this->createMock(UserHelper::class);
		$this->tableMapper = new TableMapper($this->connectionAdapter, $userHelper);
		$this->viewMapper = new ViewMapper($this->connectionAdapter, $userHelper);
	}

	public function testATableIsFoundByItsUuid(): void {
		$tableId = $this->createTestTable(['uuid' => 'c8a3f1d2-0000-4000-8000-000000000001'])['id'];

		$this->assertSame($tableId, $this->tableMapper->findByUuid('c8a3f1d2-0000-4000-8000-000000000001')?->getId());
	}

	public function testAnUnknownTableUuidFindsNothing(): void {
		$this->createTestTable(['uuid' => 'c8a3f1d2-0000-4000-8000-000000000001']);

		$this->assertNull($this->tableMapper->findByUuid('c8a3f1d2-0000-4000-8000-00000000ffff'));
	}

	public function testAViewIsFoundByItsUuid(): void {
		$tableId = $this->createTestTable()['id'];
		$viewId = $this->createView($tableId, 'c8a3f1d2-0000-4000-8000-000000000002');

		$this->assertSame($viewId, $this->viewMapper->findByUuid('c8a3f1d2-0000-4000-8000-000000000002')?->getId());
	}

	public function testAnUnknownViewUuidFindsNothing(): void {
		$this->createView($this->createTestTable()['id'], 'c8a3f1d2-0000-4000-8000-000000000002');

		$this->assertNull($this->viewMapper->findByUuid('c8a3f1d2-0000-4000-8000-00000000ffff'));
	}

	private function createView(int $tableId, string $uuid): int {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_views')
			->values([
				'table_id' => $qb->createNamedParameter($tableId),
				'title' => $qb->createNamedParameter('Open requests'),
				'description' => $qb->createNamedParameter(''),
				'uuid' => $qb->createNamedParameter($uuid),
				'created_by' => $qb->createNamedParameter('user1'),
				'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
				'last_edit_by' => $qb->createNamedParameter('user1'),
				'last_edit_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
			])
			->executeStatement();
		return (int)$this->connection->lastInsertId();
	}
}
