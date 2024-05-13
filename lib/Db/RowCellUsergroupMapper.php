<?php

namespace OCA\Tables\Db;

use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellUsergroup, string, string> */
class RowCellUsergroupMapper extends RowCellMapperSuper {
	protected string $table = 'tables_row_cells_usergroup';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellUsergroup::class);
	}

	/**
	 * @inheritDoc
	 */
	public function parseValueIncoming(Column $column, $value): string {
		// need to convert from array to string before saving
		// TODO figure out whether to handle these conversions in the BE or FE; confusing to have both
		return json_encode($value);
	}

	/**
	 * @inheritDoc
	 */
	public function parseValueOutgoing(Column $column, $value) {
		return json_decode($value);
	}
}
