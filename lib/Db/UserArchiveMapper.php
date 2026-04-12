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
	 * Fetch all per-user archive overrides for a given node.
	 *
	 * @return UserArchive[]
	 * @throws Exception
	 */
	public function findAllForNode(int $nodeType, int $nodeId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		return $this->findEntities($qb);
	}

	/**
	 * Fetch all per-user archive overrides for a given user and node type,
	 * filtered to a specific set of node IDs.
	 *
	 * Oracle enforces a hard limit of 1000 items per IN clause. The method
	 * chunks $nodeIds into batches of 997 (matching the existing
	 * ShareMapper::findAllSharesFor() pattern) and merges results in PHP to
	 * stay within this limit transparently.
	 *
	 * @param int[] $nodeIds IDs to restrict the lookup to
	 * @return array<int, UserArchive> Keyed by node_id for O(1) map lookup
	 * @throws Exception
	 */
	public function findAllOverridesForUser(string $userId, int $nodeType, array $nodeIds): array {
		if (empty($nodeIds)) {
			return [];
		}

		$results = [];
		foreach (array_chunk($nodeIds, 997) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->table)
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->in('node_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));

			foreach ($this->findEntities($qb) as $entity) {
				$results[$entity->getNodeId()] = $entity;
			}
		}

		return $results;
	}

	/**
	 * Insert or update a per-user archive override.
	 *
	 * @throws Exception
	 */
	public function upsert(string $userId, int $nodeType, int $nodeId, bool $archived): void {
		$existing = $this->findForUser($userId, $nodeType, $nodeId);

		if ($existing !== null) {
			$existing->setArchived($archived);
			$this->update($existing);
		} else {
			$entity = new UserArchive();
			$entity->setUserId($userId);
			$entity->setNodeType($nodeType);
			$entity->setNodeId($nodeId);
			$entity->setArchived($archived);
			$this->insert($entity);
		}
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
	 * Remove all per-user archive overrides for a node (used when an owner
	 * archives/unarchives or when the node is permanently deleted).
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
