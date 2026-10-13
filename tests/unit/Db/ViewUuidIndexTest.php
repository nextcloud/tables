<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\Exception;

/**
 * The uuid of a view is unique on its own, also when the view has no table.
 */
class ViewUuidIndexTest extends DatabaseTestCase {
	private const UUID = '0199c8a0-1a2b-7c3d-8e4f-123456789abc';

	protected function setUp(): void {
		parent::setUp();
		$this->cleanupTablesData();
	}

	public function testASecondViewWithoutATableCannotReuseAUuid(): void {
		$this->insertViewWithoutTable('First');

		try {
			$this->insertViewWithoutTable('Second');
			$this->fail('A second view with the same uuid was stored');
		} catch (Exception $e) {
			$this->assertSame(Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION, $e->getReason());
		}
	}

	private function insertViewWithoutTable(string $title): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_views')
			->values([
				'uuid' => $qb->createNamedParameter(self::UUID),
				'title' => $qb->createNamedParameter($title),
				'description' => $qb->createNamedParameter(''),
				'type' => $qb->createNamedParameter('grid'),
				'created_by' => $qb->createNamedParameter('user1'),
				'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
				'last_edit_by' => $qb->createNamedParameter('user1'),
				'last_edit_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
			])
			->executeStatement();
	}
}
