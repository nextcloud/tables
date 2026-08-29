<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCA\Tables\Constants\ShareReceiverType;
use OCA\Tables\Service\ValueObject\ShareToken;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewQuery;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<Share> */
class ShareMapper extends QBMapper {
	protected string $table = 'tables_shares';

	public function __construct(
		protected LoggerInterface $logger,
		IDBConnection $db,
	) {
		parent::__construct($db, $this->table, Share::class);
	}

	/**
	 * @param int $id
	 *
	 * @return Share
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Share {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByToken(ShareToken $token): Share {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('token', $qb->createNamedParameter((string)$token, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}

	/**
	 * find share for a node
	 * look for all receiver types or limit it to one given type
	 *
	 * @param int $nodeId
	 * @param string $nodeType
	 * @param string $receiver
	 * @param string|null $receiverType
	 *
	 * @return Share
	 *
	 * @throws Exception
	 */
	public function findShareForNode(int $nodeId, string $nodeType, string $receiver, ?string $receiverType = null): ?Share {
		// if shared with user
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($receiver, IQueryBuilder::PARAM_STR)));
		if ($receiverType) {
			$qb->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType, IQueryBuilder::PARAM_STR)));
		}

		$items = $this->findEntities($qb);
		return $items[0] ?? null;
	}

	/**
	 * @param string $nodeType
	 * @param string[]|int[] $receivers
	 * @param string $userId
	 * @param string|null $receiverType
	 *
	 * @return Share[]
	 *
	 * @throws Exception
	 */
	public function findAllSharesFor(string $nodeType, array $receivers, string $userId, ?string $receiverType = 'user'): array {
		if (!$receivers) {
			return [];
		}

		$chunks = [];
		// deduct extra parameters (sender, node type, receiver type)
		foreach (array_chunk($receivers, 1000 - 3) as $receiversChunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->table)
				->where($qb->expr()->in('receiver', $qb->createNamedParameter($receiversChunk, IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($qb->expr()->neq('sender', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType)))
				->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType)));

			$chunks[] = $this->findEntities($qb);
		}

		return array_merge(...$chunks);
	}

	/**
	 * @param string $nodeType
	 * @param int $nodeId
	 * @param string $sender
	 * @param array<string> $excluded receiver types to exclude from results
	 * @return Share[]
	 * @throws Exception
	 */
	public function findAllSharesForNode(string $nodeType, int $nodeId, string $sender = '', array $excluded = []): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		if (!empty($excluded)) {
			$qb->andWhere($qb->expr()->notIn('receiver_type', $qb->createNamedParameter($excluded, IQueryBuilder::PARAM_STR_ARRAY)));
		}

		return $this->findEntities($qb);
	}

	/**
	 * @param string $nodeType
	 * @param int $nodeId
	 * @param string $receiver
	 * @param string|null $receiverType
	 * @return array
	 * @throws Exception
	 */
	public function findAllSharesForNodeFor(string $nodeType, int $nodeId, string $receiver, ?string $receiverType = 'user'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('receiver', $qb->createNamedParameter($receiver, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findAllSharesForNodeTo(string $nodeType, int $nodeId, string $receivingUser, array $receivingGroups, array $receivingCircles): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);

		$orX = $qb->expr()->orX(
			$qb->expr()->andX(
				$qb->expr()->eq('receiver', $qb->createNamedParameter($receivingUser, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('receiver_type', $qb->createNamedParameter('user', IQueryBuilder::PARAM_STR)),
			)
		);

		if (!empty($receivingGroups)) {
			$orX->add(
				$qb->expr()->andX(
					$qb->expr()->in('receiver', $qb->createNamedParameter($receivingGroups, IQueryBuilder::PARAM_STR_ARRAY)),
					$qb->expr()->eq('receiver_type', $qb->createNamedParameter('group', IQueryBuilder::PARAM_STR)),
				)
			);
		}

		if (!empty($receivingCircles)) {
			$orX->add(
				$qb->expr()->andX(
					$qb->expr()->in('receiver', $qb->createNamedParameter($receivingCircles, IQueryBuilder::PARAM_STR_ARRAY)),
					$qb->expr()->eq('receiver_type', $qb->createNamedParameter('circle', IQueryBuilder::PARAM_STR)),
				)
			);
		}

		$qb->where($orX)
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int $nodeId
	 * @param string $nodeType
	 * @throws Exception
	 */
	public function deleteByNode(int $nodeId, string $nodeType):void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}

	/**
	 * @param int[] $tableIds
	 * @param int[] $contextIds
	 *
	 * @return Share[]
	 */
	public function findAllSharesForTablesAndContexts(array $tableIds, array $contextIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);

		$orX = $qb->expr()->orX();
		if (!empty($tableIds)) {
			$orX->add(
				$qb->expr()->andX(
					$qb->expr()->eq('node_type', $qb->createNamedParameter('table', IQueryBuilder::PARAM_STR)),
					$qb->expr()->in('node_id', $qb->createNamedParameter($tableIds, IQueryBuilder::PARAM_INT_ARRAY))
				)
			);
		}
		if (!empty($contextIds)) {
			$orX->add(
				$qb->expr()->andX(
					$qb->expr()->eq('node_type', $qb->createNamedParameter('context', IQueryBuilder::PARAM_STR)),
					$qb->expr()->in('node_id', $qb->createNamedParameter($contextIds, IQueryBuilder::PARAM_INT_ARRAY))
				)
			);
		}
		$qb->where($orX);
		return $this->findEntities($qb);
	}

	/**
	 * Sort fields of the share-review contract mapped to their column. The
	 * object sort is the polymorphic node name (see shareReviewNodeName()) and
	 * resolved separately; user input never reaches the query other than
	 * through this whitelist and bound parameters.
	 */
	private const SHARE_REVIEW_SORT_COLUMNS = [
		ShareReviewQuery::SORT_TIME => 's.last_edit_at',
		ShareReviewQuery::SORT_INITIATOR => 's.sender',
		ShareReviewQuery::SORT_RECIPIENT => 's.receiver',
		ShareReviewQuery::SORT_TYPE => 's.receiver_type',
	];

	/**
	 * Fetch one page of share rows with the shared node's name for ShareReview,
	 * sorted, searched and filtered as the query demands.
	 *
	 * @param list<string>|null $receiverTypes native receiver types the row must
	 *                                         have one of; null = no type filter,
	 *                                         [] = nothing matches
	 * @param list<string>|null $permissionColumns permission columns the row must
	 *                                             have at least one of set
	 *                                             (permission_read selects the rows
	 *                                             read is implied for); null = no
	 *                                             filter, [] = nothing matches
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findPageForShareReview(ShareReviewQuery $query, ?array $receiverTypes = null, ?array $permissionColumns = null): array {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$this->applyShareReviewFilters($qb, $query, $receiverTypes, $permissionColumns);
		$this->applyShareReviewOrder($qb, $query);
		$qb->setFirstResult($query->offset)
			->setMaxResults($query->limit);
		$result = $qb->executeQuery();
		$rows = $result->fetchAllAssociative();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * One share row with the shared node's name, in the findPageForShareReview()
	 * shape.
	 *
	 * @return array<string, mixed>|null
	 * @throws Exception
	 */
	public function findForShareReview(int $id): ?array {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$qb->where($qb->expr()->eq('s.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();
		return $row === false ? null : $row;
	}

	/**
	 * Count all shares and the shares matching the query's search and filters.
	 * The filtered count is only computed when the query narrows the result.
	 *
	 * @param list<string>|null $receiverTypes see findPageForShareReview()
	 * @param list<string>|null $permissionColumns see findPageForShareReview()
	 * @throws Exception
	 */
	public function countForShareReview(ShareReviewQuery $query, ?array $receiverTypes = null, ?array $permissionColumns = null): ShareReviewCounts {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from($this->table);
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		if (!$query->isFiltered() && $receiverTypes === null && $permissionColumns === null) {
			return new ShareReviewCounts($total, $total);
		}
		$qb = $this->shareReviewQuery();
		$qb->select($qb->func()->count('s.id'));
		$this->applyShareReviewFilters($qb, $query, $receiverTypes, $permissionColumns);
		$result = $qb->executeQuery();
		$filtered = (int)$result->fetchOne();
		$result->closeCursor();
		return new ShareReviewCounts($total, $filtered);
	}

	/**
	 * Count the shares matching the query's search and filters per receiver type.
	 *
	 * @param list<string>|null $receiverTypes see findPageForShareReview()
	 * @param list<string>|null $permissionColumns see findPageForShareReview()
	 * @return array<string, int> native receiver type to count, zero counts omitted
	 * @throws Exception
	 */
	public function countByTypeForShareReview(ShareReviewQuery $query, ?array $receiverTypes = null, ?array $permissionColumns = null): array {
		$qb = $this->shareReviewQuery();
		$qb->select('s.receiver_type')
			->selectAlias($qb->func()->count('s.id'), 'share_count')
			->groupBy('s.receiver_type');
		$this->applyShareReviewFilters($qb, $query, $receiverTypes, $permissionColumns);
		$result = $qb->executeQuery();
		$counts = [];
		while (($row = $result->fetchAssociative()) !== false) {
			$counts[(string)$row['receiver_type']] = (int)$row['share_count'];
		}
		$result->closeCursor();
		return $counts;
	}

	/**
	 * Shares with the shared node joined per node type — a share points to a
	 * table, a view or an application (context), each in its own table.
	 */
	private function shareReviewQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$expr = $qb->expr();
		$qb->from($this->table, 's')
			->leftJoin('s', 'tables_tables', 't', $expr->andX(
				$expr->eq('s.node_id', 't.id'),
				$expr->eq('s.node_type', $qb->createNamedParameter('table')),
			))
			->leftJoin('s', 'tables_views', 'v', $expr->andX(
				$expr->eq('s.node_id', 'v.id'),
				$expr->eq('s.node_type', $qb->createNamedParameter('view')),
			))
			->leftJoin('s', 'tables_contexts_context', 'c', $expr->andX(
				$expr->eq('s.node_id', 'c.id'),
				$expr->eq('s.node_type', $qb->createNamedParameter('context')),
			));
		return $qb;
	}

	private function selectShareReviewColumns(IQueryBuilder $qb): void {
		$qb->select(
			's.id', 's.sender', 's.receiver', 's.receiver_type', 's.node_id', 's.node_type',
			's.token', 's.password',
			's.permission_read', 's.permission_create', 's.permission_update',
			's.permission_delete', 's.permission_manage',
			's.created_at', 's.last_edit_at'
		)
			->selectAlias($qb->createFunction($this->shareReviewNodeName($qb)), 'node_name');
	}

	/**
	 * The shared node's name, whichever of the three joins matched; NULL when
	 * the node no longer exists.
	 */
	private function shareReviewNodeName(IQueryBuilder $qb): string {
		return 'COALESCE(' . $qb->getColumnName('t.title') . ', ' . $qb->getColumnName('v.title') . ', ' . $qb->getColumnName('c.name') . ')';
	}

	/**
	 * Translate the share-review query into WHERE clauses. Table shares have
	 * a password but no expiration date, so the expiration filters match
	 * nothing when they ask for expiring shares.
	 *
	 * @param list<string>|null $receiverTypes see findPageForShareReview()
	 * @param list<string>|null $permissionColumns see findPageForShareReview()
	 */
	private function applyShareReviewFilters(IQueryBuilder $qb, ShareReviewQuery $query, ?array $receiverTypes, ?array $permissionColumns): void {
		$expr = $qb->expr();
		// A column that is never NULL, negated: the portable "matches nothing"
		$matchesNothing = $expr->isNull('s.id');
		$nodeName = $qb->createFunction($this->shareReviewNodeName($qb));
		// a link share's recipient is its token
		$recipientColumns = ['s.receiver', 's.token'];

		if ($query->search !== null) {
			$pattern = $this->shareReviewLikePattern($qb, $query->search);
			$qb->andWhere($expr->orX(
				$expr->iLike($nodeName, $pattern),
				$expr->iLike('s.sender', $pattern),
				...array_map(static fn (string $column): string => $expr->iLike($column, $pattern), $recipientColumns),
			));
		}
		if ($query->objectSearch !== null) {
			$qb->andWhere($expr->iLike($nodeName, $this->shareReviewLikePattern($qb, $query->objectSearch)));
		}
		if ($query->objectSearchAny !== null) {
			$qb->andWhere($query->objectSearchAny === []
				? $matchesNothing
				: $expr->orX(...array_map(fn (string $term): string => $expr->iLike($nodeName, $this->shareReviewLikePattern($qb, $term)), $query->objectSearchAny)));
		}
		$this->applyShareReviewIdentityFilter($qb, ['s.sender'], $query->initiatorSearch, $query->initiatorIds);
		$this->applyShareReviewIdentityFilter($qb, $recipientColumns, $query->recipientSearch, $query->recipientIds);

		if ($query->modifiedSinceTimestamp !== null) {
			$qb->andWhere($expr->gt('s.last_edit_at', $qb->createNamedParameter(new \DateTime('@' . $query->modifiedSinceTimestamp), IQueryBuilder::PARAM_DATETIME_MUTABLE)));
		}
		if ($receiverTypes !== null) {
			$qb->andWhere($receiverTypes === []
				? $matchesNothing
				: $expr->in('s.receiver_type', $qb->createNamedParameter($receiverTypes, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		if ($query->tokens !== null) {
			// exact match by contract: a substring would let a caller
			// discover a token it does not have
			$qb->andWhere($query->tokens === []
				? $matchesNothing
				: $expr->in('s.token', $qb->createNamedParameter($query->tokens, IQueryBuilder::PARAM_STR_ARRAY)));
		}
		if ($query->hasPassword === true) {
			$qb->andWhere($expr->isNotNull('s.password'));
		} elseif ($query->hasPassword === false) {
			$qb->andWhere($expr->isNull('s.password'));
		}
		if ($query->hasExpiration === true || $query->expiresAfterTimestamp !== null || $query->expiresBeforeTimestamp !== null) {
			$qb->andWhere($matchesNothing);
		}
		if ($permissionColumns !== null) {
			$this->applyShareReviewPermissionFilter($qb, $permissionColumns);
		}
	}

	/**
	 * ANY-of filter on the permission columns. Read is implied for a share
	 * without any read/write flag (it still grants access to the node), so the
	 * read column also selects those rows.
	 *
	 * @param list<string> $permissionColumns
	 */
	private function applyShareReviewPermissionFilter(IQueryBuilder $qb, array $permissionColumns): void {
		$expr = $qb->expr();
		if ($permissionColumns === []) {
			$qb->andWhere($expr->isNull('s.id'));
			return;
		}
		$set = fn (string $column): string => $expr->eq('s.' . $column, $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL));
		$unset = fn (string $column): string => $expr->eq('s.' . $column, $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL));
		$predicates = [];
		foreach ($permissionColumns as $column) {
			$predicates[] = $set($column);
			if ($column === 'permission_read') {
				$predicates[] = $expr->andX($unset('permission_update'), $unset('permission_create'), $unset('permission_delete'));
			}
		}
		$qb->andWhere($expr->orX(...$predicates));
	}

	/**
	 * Scoped substring and exact id list on one identity (spread over its
	 * columns), OR-combined with each other and AND-combined with everything
	 * else.
	 *
	 * @param list<string> $columns
	 * @param list<string>|null $ids
	 */
	private function applyShareReviewIdentityFilter(IQueryBuilder $qb, array $columns, ?string $search, ?array $ids): void {
		$expr = $qb->expr();
		$predicates = [];
		if ($search !== null) {
			$pattern = $this->shareReviewLikePattern($qb, $search);
			foreach ($columns as $column) {
				$predicates[] = $expr->iLike($column, $pattern);
			}
		}
		if ($ids !== null) {
			if ($ids === []) {
				$predicates[] = $expr->isNull('s.id');
			} else {
				$parameter = $qb->createNamedParameter($ids, IQueryBuilder::PARAM_STR_ARRAY);
				foreach ($columns as $column) {
					$predicates[] = $expr->in($column, $parameter);
				}
			}
		}
		if ($predicates !== []) {
			$qb->andWhere($expr->orX(...$predicates));
		}
	}

	/**
	 * Case-insensitive substring pattern with the LIKE wildcards of the input
	 * escaped, bound as a parameter.
	 */
	private function shareReviewLikePattern(IQueryBuilder $qb, string $term): IParameter {
		return $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%');
	}

	/**
	 * ORDER BY through the sort whitelist, NULL keys last in both directions
	 * (the node name of a deleted node; databases disagree on the default),
	 * and the share id as tiebreaker in the same direction. The recipient
	 * sort key falls back to the token for link shares (their empty receiver
	 * folds to NULL), because the token is the recipient value their entries
	 * expose per the contract.
	 */
	private function applyShareReviewOrder(IQueryBuilder $qb, ShareReviewQuery $query): void {
		$direction = $query->sortDescending ? 'DESC' : 'ASC';
		if ($query->sortField === ShareReviewQuery::SORT_OBJECT) {
			$key = $this->shareReviewNodeName($qb);
		} elseif ($query->sortField === ShareReviewQuery::SORT_RECIPIENT) {
			$key = 'COALESCE(NULLIF(' . $qb->getColumnName('s.receiver') . ", ''), " . $qb->getColumnName('s.token') . ')';
		} else {
			$key = $qb->getColumnName(self::SHARE_REVIEW_SORT_COLUMNS[$query->sortField]);
		}
		if ($query->sortField !== ShareReviewQuery::SORT_TIME) {
			$qb->orderBy($qb->createFunction('CASE WHEN ' . $key . ' IS NULL THEN 1 ELSE 0 END'), 'ASC')
				->addOrderBy($qb->createFunction($key), $direction);
		} else {
			$qb->orderBy($qb->createFunction($key), $direction);
		}
		$qb->addOrderBy('s.id', $direction);
	}

	/**
	 * @throws Exception
	 */
	public function changeReceiverForNode(string $nodeType, int $nodeId, string $newReceiver, string $oldReceiver, string $sender): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->table)
			->set('receiver', $qb->createNamedParameter($newReceiver, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('sender', $qb->createNamedParameter($sender, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver', $qb->createNamedParameter($oldReceiver, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter('user', IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function deleteByReceiver(string $receiver, string $receiverType): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->delete($this->table)
			->where($qb->expr()->eq('receiver', $qb->createNamedParameter($receiver, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter($receiverType, IQueryBuilder::PARAM_STR)))
			->executeStatement();
	}

	/**
	 * All share rows with their node name, in the findPageForShareReview()
	 * shape, streamed in immutable id order so the enumeration stays stable
	 * under concurrent edits and the full list is never held in memory.
	 *
	 * @return \Generator<int, array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllForShareReview(): \Generator {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$qb->orderBy('s.id', 'ASC');
		$result = $qb->executeQuery();
		try {
			while (($row = $result->fetchAssociative()) !== false) {
				yield $row;
			}
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * @return Share[]
	 * @throws Exception
	 */
	public function findRemoteSharesForNode(int $nodeId, string $nodeType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('receiver_type', $qb->createNamedParameter(ShareReceiverType::REMOTE, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}
}
