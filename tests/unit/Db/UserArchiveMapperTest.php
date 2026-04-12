<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\UserArchive;
use OCA\Tables\Db\UserArchiveMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;

class UserArchiveMapperTest extends DatabaseTestCase {
	private UserArchiveMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = new UserArchiveMapper($this->connectionAdapter);
		$this->cleanupArchiveData();
	}

	protected function tearDown(): void {
		$this->cleanupArchiveData();
		parent::tearDown();
	}

	private function cleanupArchiveData(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('tables_archive_user')->executeStatement();
	}

	// -------------------------------------------------------------------------
	// findForUser
	// -------------------------------------------------------------------------

	public function testFindForUserReturnsNullWhenNoRecord(): void {
		$result = $this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 42);
		$this->assertNull($result);
	}

	public function testFindForUserReturnsInsertedRecord(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 42, true);

		$result = $this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 42);
		$this->assertNotNull($result);
		$this->assertTrue($result->isArchived());
		$this->assertSame('alice', $result->getUserId());
		$this->assertSame(Application::NODE_TYPE_TABLE, $result->getNodeType());
		$this->assertSame(42, $result->getNodeId());
	}

	public function testFindForUserIsScopedToUser(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 42, true);

		$result = $this->mapper->findForUser('bob', Application::NODE_TYPE_TABLE, 42);
		$this->assertNull($result);
	}

	public function testFindForUserIsScopedToNodeType(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 42, true);

		$result = $this->mapper->findForUser('alice', Application::NODE_TYPE_CONTEXT, 42);
		$this->assertNull($result);
	}

	public function testFindForUserIsScopedToNodeId(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 42, true);

		$result = $this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 99);
		$this->assertNull($result);
	}

	// -------------------------------------------------------------------------
	// upsert
	// -------------------------------------------------------------------------

	public function testUpsertInsertsNewRecord(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 1, false);

		$result = $this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 1);
		$this->assertNotNull($result);
		$this->assertFalse($result->isArchived());
	}

	public function testUpsertUpdatesExistingRecord(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 1, false);
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 1, true);

		$result = $this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 1);
		$this->assertNotNull($result);
		$this->assertTrue($result->isArchived());

		// Confirm only one row
		$all = $this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 1);
		$this->assertCount(1, $all);
	}

	public function testUpsertDoesNotAffectOtherUsers(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 1, true);
		$this->mapper->upsert('bob', Application::NODE_TYPE_TABLE, 1, false);

		$alice = $this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 1);
		$bob = $this->mapper->findForUser('bob', Application::NODE_TYPE_TABLE, 1);

		$this->assertTrue($alice->isArchived());
		$this->assertFalse($bob->isArchived());
	}

	// -------------------------------------------------------------------------
	// findAllForNode
	// -------------------------------------------------------------------------

	public function testFindAllForNodeReturnsAllUsers(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 5, true);
		$this->mapper->upsert('bob', Application::NODE_TYPE_TABLE, 5, false);
		$this->mapper->upsert('carol', Application::NODE_TYPE_TABLE, 5, true);

		$results = $this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 5);
		$this->assertCount(3, $results);
	}

	public function testFindAllForNodeIsScopedToNodeId(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 5, true);
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 6, true);

		$results = $this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 5);
		$this->assertCount(1, $results);
		$this->assertSame(5, $results[0]->getNodeId());
	}

	public function testFindAllForNodeReturnsEmptyWhenNone(): void {
		$results = $this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 999);
		$this->assertEmpty($results);
	}

	// -------------------------------------------------------------------------
	// findAllOverridesForUser
	// -------------------------------------------------------------------------

	public function testFindAllOverridesForUserReturnsKeyedByNodeId(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 10, true);
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 20, false);
		$this->mapper->upsert('bob', Application::NODE_TYPE_TABLE, 10, false); // different user

		$results = $this->mapper->findAllOverridesForUser('alice', Application::NODE_TYPE_TABLE, [10, 20, 30]);

		$this->assertArrayHasKey(10, $results);
		$this->assertArrayHasKey(20, $results);
		$this->assertArrayNotHasKey(30, $results); // no record for nodeId 30
		$this->assertTrue($results[10]->isArchived());
		$this->assertFalse($results[20]->isArchived());
	}

	public function testFindAllOverridesForUserReturnsEmptyArrayForEmptyInput(): void {
		$results = $this->mapper->findAllOverridesForUser('alice', Application::NODE_TYPE_TABLE, []);
		$this->assertSame([], $results);
	}

	public function testFindAllOverridesForUserIgnoresOtherNodeTypes(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_CONTEXT, 10, true);

		$results = $this->mapper->findAllOverridesForUser('alice', Application::NODE_TYPE_TABLE, [10]);
		$this->assertEmpty($results);
	}

	// -------------------------------------------------------------------------
	// deleteForUser
	// -------------------------------------------------------------------------

	public function testDeleteForUserRemovesRecord(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 7, true);
		$this->mapper->deleteForUser('alice', Application::NODE_TYPE_TABLE, 7);

		$result = $this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 7);
		$this->assertNull($result);
	}

	public function testDeleteForUserDoesNotAffectOtherUsers(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 7, true);
		$this->mapper->upsert('bob', Application::NODE_TYPE_TABLE, 7, false);

		$this->mapper->deleteForUser('alice', Application::NODE_TYPE_TABLE, 7);

		$this->assertNull($this->mapper->findForUser('alice', Application::NODE_TYPE_TABLE, 7));
		$this->assertNotNull($this->mapper->findForUser('bob', Application::NODE_TYPE_TABLE, 7));
	}

	public function testDeleteForUserIsNoOpWhenRecordAbsent(): void {
		// Must not throw
		$this->mapper->deleteForUser('nobody', Application::NODE_TYPE_TABLE, 999);
		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// deleteAllForNode
	// -------------------------------------------------------------------------

	public function testDeleteAllForNodeRemovesAllUsers(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 8, true);
		$this->mapper->upsert('bob', Application::NODE_TYPE_TABLE, 8, false);
		$this->mapper->upsert('carol', Application::NODE_TYPE_TABLE, 8, true);

		$this->mapper->deleteAllForNode(Application::NODE_TYPE_TABLE, 8);

		$remaining = $this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 8);
		$this->assertEmpty($remaining);
	}

	public function testDeleteAllForNodeDoesNotAffectOtherNodes(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 8, true);
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 9, false);

		$this->mapper->deleteAllForNode(Application::NODE_TYPE_TABLE, 8);

		$this->assertEmpty($this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 8));
		$this->assertCount(1, $this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 9));
	}

	public function testDeleteAllForNodeDoesNotAffectOtherNodeTypes(): void {
		$this->mapper->upsert('alice', Application::NODE_TYPE_TABLE, 8, true);
		$this->mapper->upsert('alice', Application::NODE_TYPE_CONTEXT, 8, false);

		$this->mapper->deleteAllForNode(Application::NODE_TYPE_TABLE, 8);

		$this->assertEmpty($this->mapper->findAllForNode(Application::NODE_TYPE_TABLE, 8));
		$this->assertCount(1, $this->mapper->findAllForNode(Application::NODE_TYPE_CONTEXT, 8));
	}
}
