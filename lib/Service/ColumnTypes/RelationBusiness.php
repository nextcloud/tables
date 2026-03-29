<?php

declare(strict_types=1);

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class RelationBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue($value, Column $column): string {
		// Relations are stored in the join table, not in cell values
		// This is a no-op — the value field is not used for relation columns
		return json_encode('');
	}

	public function canBeParsed($value, Column $column): bool {
		return true;
	}
}
