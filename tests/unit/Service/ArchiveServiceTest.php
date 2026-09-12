<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\UserArchive;
use OCA\Tables\Db\UserArchiveMapper;
use OCA\Tables\Service\ArchiveService;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ArchiveServiceTest extends TestCase {
	private UserArchiveMapper&MockObject $mapper;
	private IDBConnection&MockObject $connection;
	private ArchiveService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(UserArchiveMapper::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->service = new ArchiveService($this->connection, $this->mapper);
	}

	// archiveForUser

	public function testArchiveForUserOwnerSetsEntityAndClearsOverrides(): void {
		$this->mapper->expects($this->never())->method('upsert');
		$this->mapper->expects($this->once())
			->method('deleteAllForNode')
			->with(Application::NODE_TYPE_TABLE, 42);

		// connection->getQueryBuilder() called for setEntityArchived
		$qb = $this->createMockQueryBuilder();
		$this->connection->expects($this->once())
			->method('getQueryBuilder')
			->willReturn($qb);

		$this->service->archiveForUser('alice', Application::NODE_TYPE_TABLE, 42, true);
	}

	public function testArchiveForUserOwnerIsANoOpWhenAlreadyArchived(): void {
		$this->mapper->expects($this->never())->method('deleteAllForNode');
		$this->connection->expects($this->never())->method('getQueryBuilder');

		$this->service->archiveForUser('alice', Application::NODE_TYPE_TABLE, 42, true, true);
	}

	public function testArchiveForUserNonOwnerUpsertsPersonalOverride(): void {
		$this->mapper->expects($this->once())
			->method('upsert')
			->with('bob', Application::NODE_TYPE_TABLE, 42, true);
		$this->mapper->expects($this->never())->method('deleteAllForNode');
		$this->connection->expects($this->never())->method('getQueryBuilder');

		$this->service->archiveForUser('bob', Application::NODE_TYPE_TABLE, 42, false);
	}

	// unarchiveForUser

	public function testUnarchiveForUserOwnerClearsEntityAndAllOverrides(): void {
		$this->mapper->expects($this->never())->method('upsert');
		$this->mapper->expects($this->never())->method('deleteForUser');
		$this->mapper->expects($this->once())
			->method('deleteAllForNode')
			->with(Application::NODE_TYPE_TABLE, 42);

		$qb = $this->createMockQueryBuilder();
		$this->connection->expects($this->once())
			->method('getQueryBuilder')
			->willReturn($qb);

		$this->service->unarchiveForUser('alice', Application::NODE_TYPE_TABLE, 42, true, true);
	}

	public function testUnarchiveForUserOwnerIsANoOpWhenNotArchived(): void {
		$this->mapper->expects($this->never())->method('deleteAllForNode');
		$this->connection->expects($this->never())->method('getQueryBuilder');

		$this->service->unarchiveForUser('alice', Application::NODE_TYPE_TABLE, 42, true, false);
	}

	public function testUnarchiveForUserNonOwnerEntityNotArchivedDeletesOverride(): void {
		$this->mapper->expects($this->once())
			->method('deleteForUser')
			->with('bob', Application::NODE_TYPE_TABLE, 42);
		$this->mapper->expects($this->never())->method('upsert');
		$this->connection->expects($this->never())->method('getQueryBuilder');

		$this->service->unarchiveForUser('bob', Application::NODE_TYPE_TABLE, 42, false, false);
	}

	public function testUnarchiveForUserNonOwnerEntityArchivedUpsertsFalseOverride(): void {
		$this->mapper->expects($this->once())
			->method('upsert')
			->with('bob', Application::NODE_TYPE_TABLE, 42, false);
		$this->mapper->expects($this->never())->method('deleteForUser');
		$this->connection->expects($this->never())->method('getQueryBuilder');

		$this->service->unarchiveForUser('bob', Application::NODE_TYPE_TABLE, 42, false, true);
	}

	// enrichTablesWithArchiveState

	public function testEnrichTablesReplacesArchivedWithPerUserValue(): void {
		$table = $this->createTableStub(7, false);

		$override = new UserArchive();
		$override->setArchived(true);

		$this->mapper->expects($this->once())
			->method('findAllOverridesForUser')
			->with('alice', Application::NODE_TYPE_TABLE, [7])
			->willReturn([7 => $override]);

		$this->service->enrichTablesWithArchiveState([$table], 'alice');
		// Entity had archived=false but override says true
		$this->assertTrue($table->isArchived());
	}

	public function testEnrichTablesFallsBackToEntityWhenNoOverride(): void {
		$table = $this->createTableStub(7, true);

		$this->mapper->expects($this->once())
			->method('findAllOverridesForUser')
			->willReturn([]);

		$this->service->enrichTablesWithArchiveState([$table], 'alice');
		$this->assertTrue($table->isArchived());
	}

	// prepareOwnershipTransfer

	public function testPrepareOwnershipTransferNoOverrideReturnsSameArchived(): void {
		$this->mapper->expects($this->once())
			->method('findForUser')
			->with('new', Application::NODE_TYPE_TABLE, 10)
			->willReturn(null);
		$this->mapper->expects($this->never())->method('deleteForUser');
		$this->mapper->expects($this->never())->method('upsert');

		$result = $this->service->prepareOwnershipTransfer('old', 'new', Application::NODE_TYPE_TABLE, 10, false);
		$this->assertFalse($result);
	}

	public function testPrepareOwnershipTransferOverrideSameValueNoOutgoingRecord(): void {
		$override = new UserArchive();
		$override->setArchived(false); // same as entity

		$this->mapper->expects($this->once())
			->method('findForUser')
			->willReturn($override);
		$this->mapper->expects($this->once())
			->method('deleteForUser')
			->with('new', Application::NODE_TYPE_TABLE, 10);
		$this->mapper->expects($this->never())->method('upsert');

		$result = $this->service->prepareOwnershipTransfer('old', 'new', Application::NODE_TYPE_TABLE, 10, false);
		$this->assertFalse($result);
	}

	public function testPrepareOwnershipTransferOverrideDiffersPreservesOutgoingOwner(): void {
		$override = new UserArchive();
		$override->setArchived(true); // differs from entity (false)

		$this->mapper->expects($this->once())
			->method('findForUser')
			->willReturn($override);
		$deleted = [];
		$this->mapper->expects($this->exactly(2))
			->method('deleteForUser')
			->willReturnCallback(function (string $userId, int $nodeType, int $nodeId) use (&$deleted): void {
				$deleted[] = [$userId, $nodeType, $nodeId];
			});
		$this->mapper->expects($this->once())
			->method('upsert')
			->with('old', Application::NODE_TYPE_TABLE, 10, false); // preserve old owner's view

		$result = $this->service->prepareOwnershipTransfer('old', 'new', Application::NODE_TYPE_TABLE, 10, false);
		$this->assertTrue($result);
		// The new owner's override is consumed, and any stale row of the outgoing
		// owner is cleared before their preservation row is written.
		$this->assertSame([
			['new', Application::NODE_TYPE_TABLE, 10],
			['old', Application::NODE_TYPE_TABLE, 10],
		], $deleted);
	}

	// removeUserOverride

	public function testRemoveUserOverrideDeletesTheUsersRecord(): void {
		$this->mapper->expects($this->once())
			->method('deleteForUser')
			->with('old', Application::NODE_TYPE_TABLE, 10);

		$this->service->removeUserOverride('old', Application::NODE_TYPE_TABLE, 10);
	}

	// Helpers

	private function createTableStub(int $id, bool $archived): \OCA\Tables\Db\Table {
		$table = new \OCA\Tables\Db\Table();
		$table->setId($id);
		$table->setArchived($archived);
		return $table;
	}

	/**
	 * Returns a mock query builder chain that absorbs update()->set()->where()->executeStatement().
	 */
	private function createMockQueryBuilder(): \OCP\DB\QueryBuilder\IQueryBuilder {
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');

		$qb = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
		$qb->method('update')->willReturnSelf();
		$qb->method('set')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturnArgument(0);
		$qb->method('expr')->willReturn($expr);
		$qb->method('executeStatement')->willReturn(1);
		return $qb;
	}
}
