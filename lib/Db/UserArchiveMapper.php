<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<UserArchive> */
class UserArchiveMapper extends QBMapper {
	/**
	 * Oracle enforces a hard limit of 1000 items per IN clause, which is the
	 * chunk size every other mapper in this app uses.
	 */
	private const DB_CHUNK_SIZE = 1_000;

	protected string $table = 'tables_archive_user';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, UserArchive::class);
	}

	/**
	 * Look up a single per-user archive override.
	 *
	 * @throws Exception
	 */
	public function findForUser(string $userId, int $nodeType, int $nodeId): ?UserArchive {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * Which of the given users hold an override on which of the given nodes.
	 *
	 * Answers for every user in one query rather than one query per user, which
	 * matters where the caller holds the whole inherited membership of a team.
	 *
	 * @param string[] $userIds users to restrict the lookup to
	 * @param int[] $nodeIds IDs to restrict the lookup to
	 * @return array<int, list<string>> user IDs, keyed by node_id
	 * @throws Exception
	 */
	public function findOverrideUsersForNodes(array $userIds, int $nodeType, array $nodeIds): array {
		$userIds = array_values(array_unique($userIds));
		$nodeIds = array_values(array_unique($nodeIds));
		if ($userIds === [] || $nodeIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id', 'node_id')
			->from($this->table)
			->where($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('user_id', $qb->createParameter('userChunk')))
			->andWhere($qb->expr()->in('node_id', $qb->createParameter('nodeChunk')));

		$userIdsByNode = [];
		foreach (array_chunk($userIds, self::DB_CHUNK_SIZE) as $userChunk) {
			$qb->setParameter('userChunk', $userChunk, IQueryBuilder::PARAM_STR_ARRAY);
			foreach (array_chunk($nodeIds, self::DB_CHUNK_SIZE) as $nodeChunk) {
				$qb->setParameter('nodeChunk', $nodeChunk, IQueryBuilder::PARAM_INT_ARRAY);
				$result = $qb->executeQuery();
				while ($row = $result->fetchAssociative()) {
					$userIdsByNode[(int)$row['node_id']][] = (string)$row['user_id'];
				}
				$result->closeCursor();
			}
		}

		return $userIdsByNode;
	}

	/**
	 * Fetch all per-user archive overrides for a given user and node type,
	 * filtered to a specific set of node IDs.
	 *
	 * Chunks $nodeIds into batches of DB_CHUNK_SIZE and merges results in
	 * PHP to stay within the Oracle IN-clause limit transparently.
	 *
	 * @param int[] $nodeIds IDs to restrict the lookup to
	 * @return array<int, UserArchive> Keyed by node_id for O(1) map lookup
	 * @throws Exception
	 */
	public function findAllOverridesForUser(string $userId, int $nodeType, array $nodeIds): array {
		if (empty($nodeIds)) {
			return [];
		}
		$nodeIds = array_values(array_unique($nodeIds));

		$results = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('node_id', $qb->createParameter('chunk')));

		foreach (array_chunk($nodeIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('chunk', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			foreach ($this->findEntities($qb) as $entity) {
				$results[$entity->getNodeId()] = $entity;
			}
		}

		return $results;
	}

	/**
	 * Insert or update a per-user archive override.
	 *
	 * Uses IDBConnection::setValues() so insert-or-update happens as one
	 * portable atomic operation instead of a read-modify-write cycle.
	 *
	 * @throws Exception
	 */
	public function upsert(string $userId, int $nodeType, int $nodeId, bool $archived): void {
		$this->db->setValues($this->table, [
			'user_id' => $userId,
			'node_type' => $nodeType,
			'node_id' => $nodeId,
		], [
			'archived' => $archived,
		]);
	}

	/**
	 * Fetch the IDs of all users holding an archive override for a node.
	 *
	 * @return string[]
	 * @throws Exception
	 */
	public function findUserIdsForNode(int $nodeType, int $nodeId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('user_id')
			->from($this->table)
			->where($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$userIds = array_map(static fn (array $row) => (string)$row['user_id'], $result->fetchAllAssociative());
		$result->closeCursor();
		return $userIds;
	}

	/**
	 * Remove every archive override a user holds, on any node.
	 *
	 * Called when the user account is deleted, so no orphaned rows remain
	 * for nodes owned by other users.
	 *
	 * @throws Exception
	 */
	public function deleteAllForUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		$qb->executeStatement();
	}

	/**
	 * Remove the per-user archive override for a single user.
	 *
	 * @throws Exception
	 */
	public function deleteForUser(string $userId, int $nodeType, int $nodeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}

	/**
	 * Remove all per-user archive overrides for a node, which only happens once
	 * the node itself is gone. An owner archiving or unarchiving sets the shared
	 * flag and leaves every personal choice standing.
	 *
	 * @throws Exception
	 */
	public function deleteAllForNode(int $nodeType, int $nodeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}
}
