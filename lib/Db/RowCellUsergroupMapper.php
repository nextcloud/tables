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
	public function parseValueIncoming(Column $column, $value): array {
		return json_decode($value, true);
	}

	/**
	 * @inheritDoc
	 */
	public function parseValueOutgoing(Column $column, $value, ?int $value_type = null): string {
		return json_encode(['id' => $value, 'type' => $value_type]);
	}
}
