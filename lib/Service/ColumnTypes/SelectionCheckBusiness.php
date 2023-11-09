<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionCheckBusiness extends SuperBusiness implements IColumnTypeBusiness {
	public const PATTERN_POSITIVE = ['yes', '1', true, 1, 'true'];
	public const PATTERN_NEGATIVE = ['no', '0', false, 0, 'false'];

	public function parseValue(string $value, ?Column $column = null): string {
		$found = in_array($value, self::PATTERN_POSITIVE, true);
		return json_encode($found ? 'true' : 'false');
	}

	public function canBeParsed(string $value, ?Column $column = null): bool {
		return in_array($value, self::PATTERN_POSITIVE) || in_array($value, self::PATTERN_NEGATIVE) ;
	}

}
