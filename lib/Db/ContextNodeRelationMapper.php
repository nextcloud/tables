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

	public function getRelIdsForNode(int $nodeId, int $nodeType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($this->table)
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId)))
			->andWhere($qb->expr()->eq('node_type', $qb->createNamedParameter($nodeType)));
		$result = $qb->executeQuery();
		$nodeRelIds = [];
		while ($row = $result->fetch()) {
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
			->where($qb->expr()->eq('id', $qb->createNamedParameter($nodeRelId)));

		$row = $this->findOneQuery($qb);
		return $this->mapRowToEntity($row);
	}
}
