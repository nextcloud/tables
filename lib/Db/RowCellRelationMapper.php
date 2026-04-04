<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCP\IDBConnection;

/**
 * @template-extends RowCellMapperSuper<RowCellRelation, string, string|array>
 */
class RowCellRelationMapper extends RowCellMapperSuper {
	use RowCellBulkFetchTrait;

	protected string $table = 'tables_row_cells_relation';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellRelation::class);
	}

	public function filterValueToQueryParam(Column $column, mixed $value): mixed {
		return $value ?? '';
	}

	public function applyDataToEntity(Column $column, RowCellSuper $cell, $data): void {
		if (is_array($data)) {
			$cell->setValue(json_encode($data));
		} else {
			$cell->setValue($data);
		}
	}

	public function formatEntity(Column $column, RowCellSuper $cell) {
		return json_decode($cell->getValue());
	}
}
