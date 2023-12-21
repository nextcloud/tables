<?php

namespace OCA\Tables\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellNumber, int|float|null, int|float|null> */
class RowCellNumberMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_number';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellNumber::class);
	}

	/**
	 * @inheritDoc
	 */
	public function parseValueOutgoing(Column $column, $value) {
		if($value === '') {
			return null;
		}
		$decimals = $column->getNumberDecimals() ?? 0;
		if ($decimals === 0) {
			return (int) $value;
		} else {
			return round(floatval($value), $decimals);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function parseValueIncoming(Column $column, $value): ?float {
		if($value === '') {
			return null;
		}
		return (float) $value;
	}

	public function getDbParamType() {
		// seems to be a string for float/double values
		return IQueryBuilder::PARAM_STR;
	}
}
