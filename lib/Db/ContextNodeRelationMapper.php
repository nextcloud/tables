<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<ContextNodeRelation> */
class ContextNodeRelationMapper extends QBMapper {
	private const DB_CHUNK_SIZE = 1_000;

	protected string $table = 'tables_contexts_rel_context_node';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, ContextNodeRelation::class);
	}

	public function deleteAllByContextId(int $contextId): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->tableName)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();
	}

	/**
	 * The ids of the applications that hold a node.
	 *
	 * @return list<int>
	 * @throws Exception
	 */
	public function getContextIdsForNode(int $nodeId, int $nodeType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('context_id')->from($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$contextIds = [];
		while ($row = $result->fetchAssociative()) {
			$contextIds[] = (int)$row['context_id'];
		}
		$result->closeCursor();
		return $contextIds;
	}

	public function getRelIdsForNode(int $nodeId, int $nodeType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$nodeRelIds = [];
		while ($row = $result->fetchAssociative()) {
			$nodeRelIds[] = (int)$row['id'];
		}
		return $nodeRelIds;
	}

	public function deleteByNodeRelIds(array $nodeRelIds): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->in('id', $qb->createNamedParameter($nodeRelIds, IQueryBuilder::PARAM_INT_ARRAY), ':nodeRelIds'));
		$qb->executeStatement();
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function findById(int $nodeRelId): ContextNodeRelation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeRelId, IQueryBuilder::PARAM_INT)));

		$row = $this->findOneQuery($qb);
		return $this->mapRowToEntity($row);
	}

	/**
	 * The given node relations that belong to the application of a page, locked until
	 * the transaction ends.
	 *
	 * @param int[] $nodeRelIds
	 * @return int[]
	 * @throws Exception
	 */
	public function filterIdsOfPageContext(int $pageId, array $nodeRelIds): array {
		if ($nodeRelIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id')
			->from($this->table, 'r')
			->innerJoin('r', 'tables_contexts_page', 'p', $qb->expr()->eq('p.context_id', 'r.context_id'))
			->where($qb->expr()->eq('p.id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('r.id', $qb->createParameter('ids')))
			->orderBy('r.id');
		$this->lockRows($qb);

		$ids = [];
		foreach (array_chunk($nodeRelIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$result = $qb->executeQuery();
			while ($row = $result->fetchAssociative()) {
				$ids[] = (int)$row['id'];
			}
			$result->closeCursor();
		}
		return $ids;
	}

	/**
	 * Locks node relations until the transaction ends. A path that removes relations
	 * locks them before it unlinks their widgets, the way {@see self::filterIdsOfPageContext()}
	 * locks the ones a save points widgets at, so the two never interleave.
	 *
	 * @param int[] $nodeRelIds
	 * @throws Exception
	 */
	public function lockByIds(array $nodeRelIds): void {
		if ($nodeRelIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->table)
			->where($qb->expr()->in('id', $qb->createParameter('ids')))
			->orderBy('id');
		$this->lockRows($qb);
		foreach (array_chunk($nodeRelIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$qb->executeQuery()->closeCursor();
		}
	}

	/**
	 * Locks the rows a query reads until the transaction ends. SQLite has no row locks and
	 * runs one write transaction at a time anyway.
	 */
	private function lockRows(IQueryBuilder $qb): void {
		if ($this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_SQLITE) {
			$qb->forUpdate();
		}
	}
}
