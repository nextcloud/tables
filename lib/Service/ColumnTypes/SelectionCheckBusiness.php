<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionCheckBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(string $value, ?Column $column = null): string {
		$hits = ['yes', '1', true, 1];
		return json_encode(in_array($value, $hits) ? 'true' : 'false');
	}

	public function canBeParsed(string $value, ?Column $column = null): bool {
		$positive = ['yes', '1', true, 1];
		$negative = ['no', '0', false, 0];
		return in_array($value, $positive) || in_array($value, $negative) ;
	}

}
