<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Share\ShareReview\ShareReviewQuery;
use Psr\Log\LoggerInterface;

/**
 * Runs the share-review page/count queries against the database, so the SQL
 * translation of the ShareReviewQuery contract (polymorphic node join,
 * sorting, search, filters, counts, LIKE escaping) is verified.
 */
class ShareMapperShareReviewTest extends DatabaseTestCase {
	private ShareMapper $shareMapper;
	/** @var array<string, int> receiver to share id */
	private array $shareIds = [];

	protected function setUp(): void {
		parent::setUp();
		$this->cleanupTablesData();
		$this->shareMapper = new ShareMapper($this->createMock(LoggerInterface::class), $this->connectionAdapter);

		$budget = $this->insertNode('tables_tables', ['title' => 'Budget', 'ownership' => 'alice', 'created_by' => 'alice', 'last_edit_by' => 'alice']);
		$openTasks = $this->insertNode('tables_views', ['table_id' => $budget, 'title' => 'Open tasks', 'description' => '', 'created_by' => 'alice', 'last_edit_by' => 'alice']);
		$this->insertShare($budget, 'table', 'user', 'bob', edit: '2026-05-01 10:00:00', update: true);
		$this->insertShare($budget, 'table', 'link', '', edit: '2026-03-01 10:00:00', token: 'tok123', password: 'hash');
		$this->insertShare($openTasks, 'view', 'group', 'devs', edit: '2026-04-01 10:00:00', read: false);
		$this->insertShare($openTasks, 'view', 'circle', 'teamX', edit: '2026-02-01 10:00:00', manage: true, sender: 'bob');
		// the shared node is gone
		$this->insertShare(999999, 'table', 'user', 'carol', edit: '2026-01-01 10:00:00', sender: 'bob');
	}

	protected function tearDown(): void {
		$this->cleanupTablesData();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $values
	 */
	private function insertNode(string $table, array $values): int {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$bound = [];
		foreach ($values as $column => $value) {
			$bound[$column] = $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR);
		}
		$bound['created_at'] = $qb->createNamedParameter('2026-01-01 00:00:00');
		$bound['last_edit_at'] = $qb->createNamedParameter('2026-01-01 00:00:00');
		$qb->insert($table)->values($bound)->executeStatement();
		return $qb->getLastInsertId();
	}

	private function insertShare(int $nodeId, string $nodeType, string $receiverType, string $receiver, string $edit, bool $read = true, bool $update = false, bool $manage = false, ?string $token = null, ?string $password = null, string $sender = 'alice'): void {
		$qb = $this->connectionAdapter->getQueryBuilder();
		$qb->insert('tables_shares')->values([
			'sender' => $qb->createNamedParameter($sender),
			'receiver' => $qb->createNamedParameter($receiver),
			'receiver_type' => $qb->createNamedParameter($receiverType),
			'node_id' => $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT),
			'node_type' => $qb->createNamedParameter($nodeType),
			'token' => $qb->createNamedParameter($token),
			'password' => $qb->createNamedParameter($password),
			'permission_read' => $qb->createNamedParameter($read, IQueryBuilder::PARAM_BOOL),
			'permission_create' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
			'permission_update' => $qb->createNamedParameter($update, IQueryBuilder::PARAM_BOOL),
			'permission_delete' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
			'permission_manage' => $qb->createNamedParameter($manage, IQueryBuilder::PARAM_BOOL),
			'created_at' => $qb->createNamedParameter('2026-01-01 00:00:00'),
			'last_edit_at' => $qb->createNamedParameter($edit),
		])->executeStatement();
		$this->shareIds[$receiver !== '' ? $receiver : $token] = $qb->getLastInsertId();
	}

	/**
	 * @return list<string> receiver (token for links) per row, in page order
	 */
	private function recipientsOf(ShareReviewQuery $query, ?array $types = null, ?array $permissions = null): array {
		return array_map(
			static fn (array $row): string => $row['receiver_type'] === 'link' ? (string)$row['token'] : (string)$row['receiver'],
			$this->shareMapper->findPageForShareReview($query, $types, $permissions),
		);
	}

	private function filteredCount(ShareReviewQuery $query, ?array $types = null, ?array $permissions = null): int {
		return $this->shareMapper->countForShareReview($query, $types, $permissions)->filteredCount;
	}

	public function testUnfilteredCountsAreEqual(): void {
		$counts = $this->shareMapper->countForShareReview(new ShareReviewQuery());

		$this->assertSame(5, $counts->totalCount);
		$this->assertSame(5, $counts->filteredCount);
	}

	public function testDefaultSortIsLastEditDescendingWithIdTiebreaker(): void {
		$this->assertSame(['bob', 'devs', 'tok123', 'teamX', 'carol'], $this->recipientsOf(new ShareReviewQuery()));
		$this->assertSame(['carol', 'teamX', 'tok123', 'devs', 'bob'], $this->recipientsOf(new ShareReviewQuery(sortDescending: false)));
	}

	public function testPagination(): void {
		$this->assertSame(['bob', 'devs'], $this->recipientsOf(new ShareReviewQuery(limit: 2)));
		$this->assertSame(['tok123', 'teamX'], $this->recipientsOf(new ShareReviewQuery(limit: 2, offset: 2)));
		$this->assertSame(['carol'], $this->recipientsOf(new ShareReviewQuery(limit: 2, offset: 4)));
	}

	public function testObjectSortUsesThePolymorphicNameWithDeletedNodesLast(): void {
		$this->assertSame(['bob', 'tok123', 'devs', 'teamX', 'carol'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_OBJECT, sortDescending: false)));
		$this->assertSame(['teamX', 'devs', 'tok123', 'bob', 'carol'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_OBJECT, sortDescending: true)));
	}

	public function testSortByInitiatorRecipientAndType(): void {
		$this->assertSame(['bob', 'tok123', 'devs', 'teamX', 'carol'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_INITIATOR, sortDescending: false)));
		// the link share's empty receiver falls back to its token — the
		// recipient value its entry exposes — so it interleaves by token
		$this->assertSame(['bob', 'carol', 'devs', 'teamX', 'tok123'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_RECIPIENT, sortDescending: false)));
		$this->assertSame(['tok123', 'teamX', 'devs', 'carol', 'bob'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_RECIPIENT, sortDescending: true)));
		$this->assertSame(['teamX', 'devs', 'tok123', 'bob', 'carol'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_TYPE, sortDescending: false)));
	}

	public function testSearchSpansNodeNameSenderReceiverAndToken(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(search: 'BUDGET')));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(search: 'open')));
		// sender bob (2 shares) plus receiver bob
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(search: 'bob')));
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(search: 'tok1')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(search: '%')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(search: '_')));
	}

	public function testObjectSearchAnyMatchesAnyOfThePatterns(): void {
		$this->assertSame(4, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['BUDGET', 'tasks'])));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['tasks', 'nomatch'])));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['tasks'], objectSearch: 'budget')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(objectSearchAny: [])));
	}

	public function testScopedFiltersAndIdLists(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(objectSearch: 'tasks')));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(initiatorSearch: 'BO')));
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(recipientSearch: 'tok')));
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(initiatorIds: ['alice'])));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(initiatorSearch: 'bo', initiatorIds: ['alice'])));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(recipientIds: ['bob', 'tok123'])));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(recipientIds: [])));
	}

	public function testTypePasswordAndExpirationFilters(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(), ['user']));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(), ['link', 'circle']));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(), []));
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(hasPassword: true)));
		$this->assertSame(4, $this->filteredCount(new ShareReviewQuery(hasPassword: false)));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(hasExpiration: true)));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(expiresAfterTimestamp: 1)));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(hasExpiration: false)));
	}

	public function testPermissionFilterTreatsReadAsImpliedWithoutWriteFlags(): void {
		$query = new ShareReviewQuery();
		$this->assertSame(1, $this->filteredCount($query, null, ['permission_manage']));
		$this->assertSame(1, $this->filteredCount($query, null, ['permission_update']));
		// explicit read on four rows plus the flagless 'devs' row where read is implied
		$this->assertSame(5, $this->filteredCount($query, null, ['permission_read']));
		$this->assertSame(0, $this->filteredCount($query, null, []));
	}

	public function testModifiedSinceIsStrictOnLastEdit(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(modifiedSinceTimestamp: strtotime('2026-03-01 10:00:00'))));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(modifiedSinceTimestamp: strtotime('2025-12-31 00:00:00'))));
	}

	public function testCountByTypeAppliesFilters(): void {
		$byType = $this->shareMapper->countByTypeForShareReview(new ShareReviewQuery());
		ksort($byType);
		$this->assertSame(['circle' => 1, 'group' => 1, 'link' => 1, 'user' => 2], $byType);
		$this->assertSame(['user' => 1, 'circle' => 1], array_intersect_key($this->shareMapper->countByTypeForShareReview(new ShareReviewQuery(initiatorIds: ['bob'])), ['user' => 1, 'circle' => 1]));
	}

	public function testFindForShareReviewCarriesTheNodeName(): void {
		$row = $this->shareMapper->findForShareReview($this->shareIds['teamX']);

		$this->assertNotNull($row);
		$this->assertSame('teamX', $row['receiver']);
		$this->assertSame('Open tasks', $row['node_name']);
		$this->assertNull($this->shareMapper->findForShareReview($this->shareIds['carol'])['node_name'], 'a deleted node has no name');
		$this->assertNull($this->shareMapper->findForShareReview(0));
	}
}
