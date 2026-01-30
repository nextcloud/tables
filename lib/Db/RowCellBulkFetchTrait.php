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

		$chunkSize = 500;
		$results = [];
		foreach (array_chunk($rowIds, $chunkSize) as $rowIdChunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->table)
				->where($qb->expr()->in('row_id', $qb->createNamedParameter($rowIdChunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->in('column_id', $qb->createNamedParameter($columnIds, IQueryBuilder::PARAM_INT_ARRAY)));

			$chunkResults = $this->findEntities($qb);
			if (!empty($chunkResults)) {
				$results = array_merge($results, $chunkResults);
			}
		}
		return $results;
	}
}
