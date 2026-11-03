<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<MenuItem> */
class MenuItemMapper extends QBMapper {
	protected string $table = 'tables_contexts_menu_item';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, MenuItem::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findById(int $itemId): MenuItem {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return list<MenuItem> in menu order
	 * @throws Exception
	 */
	public function findByContextId(int $contextId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)))
			->orderBy('order', 'ASC')
			->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Moves the items of a group to the top level.
	 *
	 * @throws Exception
	 */
	public function clearParent(int $parentId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->table)
			->set('parent_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('parent_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function deleteByContextId(int $contextId): void {
		$this->deleteWhereIn('context_id', [$contextId]);
	}

	/**
	 * @param int[] $pageIds
	 * @throws Exception
	 */
	public function deleteByPageIds(array $pageIds): void {
		$this->deleteWhereIn('page_id', $pageIds);
	}

	/**
	 * @param int[] $nodeRelIds
	 * @throws Exception
	 */
	public function deleteByNodeRelIds(array $nodeRelIds): void {
		$this->deleteWhereIn('node_rel_id', $nodeRelIds);
	}

	/**
	 * @param int[] $values
	 * @throws Exception
	 */
	private function deleteWhereIn(string $column, array $values): void {
		if ($values === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->in($column, $qb->createParameter('values')));
		foreach (array_chunk($values, 1_000) as $chunk) {
			$qb->setParameter('values', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$qb->executeStatement();
		}
	}
}
