<?php

namespace OCA\Tables\Db;

use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellDatetime, string, string> */
class RowCellDatetimeMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_datetime';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellDatetime::class);
	}
}
