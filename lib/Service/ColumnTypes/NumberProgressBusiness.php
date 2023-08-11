<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class NumberProgressBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(string $value, ?Column $column = null): string {
		return json_encode((int) $value);
	}

	public function canBeParsed(string $value, ?Column $column = null): bool {
		return !$value || ((int) $value >= 0 && (int) $value <= 100);
	}

}
