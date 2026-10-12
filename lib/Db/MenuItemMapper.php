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

/** @template-extends QBMapper<MenuItem> */
class MenuItemMapper extends QBMapper {
	private const DB_CHUNK_SIZE = 1_000;

	protected string $table = 'tables_contexts_menu_item';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, MenuItem::class);
	}

	/**
	 * @return MenuItem[] ordered by their position in the menu
	 * @throws Exception
	 */
	public function findByContextId(int $contextId): array {
		return $this->findByContextIds([$contextId])[$contextId] ?? [];
	}

	/**
	 * The menu items of the given contexts, each list ordered by position. An item whose
	 * view or table no longer exists is marked broken.
	 *
	 * @param int[] $contextIds
	 * @return array<int, list<MenuItem>> keyed by context id
	 * @throws Exception
	 */
	public function findByContextIds(array $contextIds): array {
		if ($contextIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$brokenExpression = sprintf(
			'CASE WHEN (m.target_type = %s AND v.id IS NULL) OR (m.target_type = %s AND t.id IS NULL) THEN 1 ELSE 0 END',
			$qb->createNamedParameter(MenuItem::TARGET_VIEW),
			$qb->createNamedParameter(MenuItem::TARGET_TABLE),
		);
		$qb->select('m.*')
			->selectAlias($qb->createFunction($brokenExpression), 'broken')
			->from($this->table, 'm')
			->leftJoin('m', 'tables_views', 'v', $qb->expr()->andX(
				$qb->expr()->eq('v.id', 'm.target_id'),
				$qb->expr()->eq('m.target_type', $qb->createNamedParameter(MenuItem::TARGET_VIEW)),
			))
			->leftJoin('m', 'tables_tables', 't', $qb->expr()->andX(
				$qb->expr()->eq('t.id', 'm.target_id'),
				$qb->expr()->eq('m.target_type', $qb->createNamedParameter(MenuItem::TARGET_TABLE)),
			))
			->where($qb->expr()->in('m.context_id', $qb->createParameter('contextIds')))
			->orderBy('m.context_id', 'ASC')
			->addOrderBy('m.order', 'ASC')
			->addOrderBy('m.id', 'ASC');

		$byContext = [];
		foreach (array_chunk(array_values(array_unique($contextIds)), self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('contextIds', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			foreach ($this->findEntities($qb) as $menuItem) {
				$byContext[$menuItem->getContextId()][] = $menuItem;
			}
		}
		return $byContext;
	}

	/**
	 * @throws Exception
	 */
	public function deleteAllByContextId(int $contextId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->eq('context_id', $qb->createNamedParameter($contextId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
