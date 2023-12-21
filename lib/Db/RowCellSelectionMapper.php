<?php

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

	/**
	 * @inheritDoc
	 */
	public function parseValueIncoming(Column $column, $value): string {
		return json_encode($value);
	}

	/**
	 * @inheritDoc
	 */
	public function parseValueOutgoing(Column $column, $value) {
		return json_decode($value);
	}
}
