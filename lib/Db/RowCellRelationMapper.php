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
	private const DB_CHUNK_SIZE = 1_000;

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

	/**
	 * @param list<int|null> $values
	 * @return list<int>|int|null
	 */
	public function formatAggregatedValues(Column $column, array $values): mixed {
		$values = array_values(array_filter($values, static fn ($value) => $value !== null));
		if (!(bool)($column->getCustomSettingsArray()[Column::RELATION_ALLOW_MULTIPLE] ?? false)) {
			return $values[0] ?? null;
		}
		return $values;
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
				if (count($idsToDelete) >= self::DB_CHUNK_SIZE) {
					$this->deleteByIds($idsToDelete);
					$idsToDelete = [];
				}
			} else {
				$seenRows[$rowId] = true;
			}
		}
		$result->closeCursor();

		if ($idsToDelete !== []) {
			$this->deleteByIds($idsToDelete);
		}
	}

	/**
	 * @param list<int> $ids
	 */
	private function deleteByIds(array $ids): void {
		$deleteQb = $this->db->getQueryBuilder();
		$deleteQb->delete($this->table)
			->where($deleteQb->expr()->in('id', $deleteQb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$deleteQb->executeStatement();
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
