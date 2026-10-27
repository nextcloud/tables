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

/** @template-extends QBMapper<Widget> */
class WidgetMapper extends QBMapper {
	protected string $table = 'tables_contexts_widget';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Widget::class);
	}

	/**
	 * @param int[] $pageIds
	 * @return Widget[] ordered by page and grid position
	 * @throws Exception
	 */
	public function findByPageIds(array $pageIds): array {
		if ($pageIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->in('page_id', $qb->createNamedParameter($pageIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('page_id', 'ASC')
			->addOrderBy('grid_y', 'ASC')
			->addOrderBy('grid_x', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @return int[]
	 * @throws Exception
	 */
	public function findIdsByPageId(int $pageId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->table)
			->where($qb->expr()->eq('page_id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$ids = [];
		while ($row = $result->fetchAssociative()) {
			$ids[] = (int)$row['id'];
		}
		$result->closeCursor();
		return $ids;
	}

	/**
	 * @param int[] $widgetIds
	 * @throws Exception
	 */
	public function deleteByIds(array $widgetIds): void {
		if ($widgetIds === []) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->table)
			->where($qb->expr()->in('id', $qb->createNamedParameter($widgetIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();
	}
}
