<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\IDBConnection;

/**
 * @template-extends RowCellMapperSuper<RowCellSelection, string, string|array>
 */
class RowCellSelectionMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_selection';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellSelection::class);
	}

	public function filterValueToQueryParam(Column $column, mixed $value): mixed {
		return $this->valueToJsonDbValue($column, $value);
	}

	public function applyDataToEntity(Column $column, RowCellSuper $cell, $data): void {
		$cell->setValue($this->valueToJsonDbValue($column, $data));
	}

	public function formatEntity(Column $column, RowCellSuper $cell) {
		return json_decode($cell->getValue());
	}

	private function valueToJsonDbValue(Column $column, $value): string {
		if ($column->getSubtype() === 'check') {
			return json_encode(ltrim($value, '"'));
		}

		if ($column->getSubtype() === '' || $column->getSubtype() === null) {
			return $value ?? '';
		}

		return json_encode($value);
	}
}
