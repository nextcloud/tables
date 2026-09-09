<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellRelation, int|null, int|null> */
class RowCellRelationMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_relation';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellRelation::class);
	}

	/**
	 * Relation values are stored as one cell row per related id (usergroup-style),
	 * so single- and multi-select columns share the same storage shape.
	 */
	public function hasMultipleValues(): bool {
		return true;
	}

	public function getDbParamType() {
		return IQueryBuilder::PARAM_INT;
	}

	public function formatRowData(Column $column, array $row) {
		$value = $row['value'];
		if ($value === null || $value === '') {
			return null;
		}
		return (int)$value;
	}

	public function applyDataToEntity(Column $column, RowCellSuper $cell, $data): void {
		$cell->setValue($data === null || $data === '' ? null : (int)$data);
	}

	/**
	 * Keep only the first related value per row (lowest cell id).
	 * Used when allowMultiple is turned off on an existing column.
	 */
	public function truncateToSingleValuePerRow(int $columnId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'row_id')
			->from($this->table)
			->where($qb->expr()->eq('column_id', $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)))
			->orderBy('row_id', 'ASC')
			->addOrderBy('id', 'ASC');

		$result = $qb->executeQuery();
		$seenRows = [];
		$idsToDelete = [];
		while ($row = $result->fetchAssociative()) {
			$rowId = (int)$row['row_id'];
			if (isset($seenRows[$rowId])) {
				$idsToDelete[] = (int)$row['id'];
			} else {
				$seenRows[$rowId] = true;
			}
		}
		$result->closeCursor();

		foreach (array_chunk($idsToDelete, 500) as $chunk) {
			$deleteQb = $this->db->getQueryBuilder();
			$deleteQb->delete($this->table)
				->where($deleteQb->expr()->in('id', $deleteQb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$deleteQb->executeStatement();
		}
	}

	/**
	 * Whether any table row has no related value for this column.
	 */
	public function hasRowsWithoutValue(int $columnId, int $tableId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('sl.id')
			->from('tables_row_sleeves', 'sl')
			->leftJoin('sl', $this->table, 'c', $qb->expr()->andX(
				$qb->expr()->eq('sl.id', 'c.row_id'),
				$qb->expr()->eq('c.column_id', $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->isNotNull('c.value'),
			))
			->where($qb->expr()->eq('sl.table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('c.id'))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$hasEmpty = $result->fetchOne() !== false;
		$result->closeCursor();
		return $hasEmpty;
	}
}
