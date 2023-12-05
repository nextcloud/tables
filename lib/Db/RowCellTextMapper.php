<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends RowCellMapperSuper<RowCellText> */
class RowCellTextMapper extends RowCellMapperSuper implements IRowCellMapper {
	protected string $table = 'tables_row_cells_text';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowCellText::class);
	}
}
