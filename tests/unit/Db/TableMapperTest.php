<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\TableMapper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

class TableMapperTest extends DatabaseTestCase {
	private TableMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanupTablesData();
		$this->mapper = new TableMapper($this->connectionAdapter, $this->createMock(UserHelper::class));
	}

	protected function tearDown(): void {
		$this->cleanupTablesData();
		parent::tearDown();
	}

	public function testTouchUpdatesLastEditAtAndBy(): void {
		$table = $this->createTestTable(['last_edit_at' => '2020-01-01 00:00:00', 'last_edit_by' => 'user1']);

		$this->mapper->touch($table['id'], 'user2', new \DateTime('2030-05-01 12:00:00'));

		$updated = $this->mapper->find($table['id']);
		$this->assertSame('2030-05-01 12:00:00', $updated->getLastEditAt());
		$this->assertSame('user2', $updated->getLastEditBy());
	}

	public function testTouchWithoutUserIdKeepsExistingLastEditBy(): void {
		$table = $this->createTestTable(['last_edit_at' => '2020-01-01 00:00:00', 'last_edit_by' => 'user1']);

		$this->mapper->touch($table['id'], null, new \DateTime('2030-05-01 12:00:00'));

		$updated = $this->mapper->find($table['id']);
		$this->assertSame('2030-05-01 12:00:00', $updated->getLastEditAt());
		$this->assertSame('user1', $updated->getLastEditBy());
	}

	public function testTouchInvalidatesMapperCache(): void {
		$table = $this->createTestTable(['last_edit_at' => '2020-01-01 00:00:00']);
		$this->mapper->find($table['id']); // warms the cache

		$this->mapper->touch($table['id'], 'user2', new \DateTime('2030-05-01 12:00:00'));

		$updated = $this->mapper->find($table['id']);
		$this->assertSame('2030-05-01 12:00:00', $updated->getLastEditAt());
	}
}
