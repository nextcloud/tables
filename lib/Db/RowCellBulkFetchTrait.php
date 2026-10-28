<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;

trait RowCellBulkFetchTrait {
	/**
	 * @param int[] $rowIds
	 * @param int[] $columnIds
	 *
	 * @return array
	 */
	public function findAllByRowIdsAndColumnIds(array $rowIds, array $columnIds): array {
		if (empty($rowIds) || empty($columnIds)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->in('row_id', $qb->createParameter('rowIds')))
			->andWhere($qb->expr()->in('column_id', $qb->createParameter('columnIds')));

		$results = [];
		foreach (array_chunk($columnIds, self::DB_CHUNK_SIZE) as $columnIdChunk) {
			$qb->setParameter('columnIds', $columnIdChunk, IQueryBuilder::PARAM_INT_ARRAY);
			foreach (array_chunk($rowIds, self::DB_CHUNK_SIZE) as $rowIdChunk) {
				$qb->setParameter('rowIds', $rowIdChunk, IQueryBuilder::PARAM_INT_ARRAY);
				$results[] = $this->findEntities($qb);
			}
		}
		return array_merge(...$results);
	}
}
