<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<Column> */
class RowCellNumberMapper extends RowCellMapperSuper implements IRowCellMapper {
	protected string $table = 'tables_row_cells_number';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellNumber::class);
	}

	public function parseValueOutgoing(Column $column, $value) {
		if($value === '') {
			return null;
		}
		$decimals = $column->getNumberDecimals() ?? 0;
		if ($decimals === 0) {
			return intval($value);
		} else {
			return round(floatval($value), $decimals);
		}
	}
}
