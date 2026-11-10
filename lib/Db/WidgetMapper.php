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
	private const DB_CHUNK_SIZE = 1_000;

	protected string $table = 'tables_contexts_widget';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Widget::class);
	}

	/**
	 * @param int[] $pageIds
	 * @param bool $forUpdate lock the rows until the transaction ends
	 * @return Widget[] ordered by page and grid position
	 * @throws Exception
	 */
	public function findByPageIds(array $pageIds, bool $forUpdate = false): array {
		if ($pageIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->in('page_id', $qb->createParameter('pageIds')))
			->orderBy('page_id', 'ASC')
			->addOrderBy('grid_y', 'ASC')
			->addOrderBy('grid_x', 'ASC')
			->addOrderBy('id', 'ASC');
		// SQLite has no row locks and runs one write transaction at a time anyway
		if ($forUpdate && $this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_SQLITE) {
			$qb->forUpdate();
		}

		$widgets = [];
		foreach (array_chunk($pageIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('pageIds', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			array_push($widgets, ...$this->findEntities($qb));
		}
		return $widgets;
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
			->where($qb->expr()->in('id', $qb->createParameter('ids')));
		foreach (array_chunk($widgetIds, self::DB_CHUNK_SIZE) as $chunk) {
			$qb->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$qb->executeStatement();
		}
	}
}
