<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class NumberBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(string $value, ?Column $column = null): string {
		return json_encode(floatval($value));
	}

	public function canBeParsed(string $value, ?Column $column = null): bool {
		return !$value || floatval($value);
	}

}
