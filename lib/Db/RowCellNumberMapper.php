<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellNumber, int|float|null, int|float|null> */
class RowCellNumberMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_number';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellNumber::class);
	}

	public function formatEntity(Column $column, RowCellSuper $cell) {
		$value = $cell->getValue();
		if ($value === '') {
			return null;
		}
		$decimals = $column->getNumberDecimals() ?? 0;
		if ($decimals === 0) {
			return (int)$value;
		} else {
			return round(floatval($value), $decimals);
		}
	}

	public function applyDataToEntity(Column $column, RowCellSuper $cell, $data): void {
		if (!is_numeric($data)) {
			$cell->setValueWrapper(null);
		}
		$cell->setValueWrapper((float)$data);
	}

	public function getDbParamType() {
		// seems to be a string for float/double values
		return IQueryBuilder::PARAM_STR;
	}
}
