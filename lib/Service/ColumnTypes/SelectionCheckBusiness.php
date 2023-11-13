<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class SelectionCheckBusiness extends SuperBusiness implements IColumnTypeBusiness {
	public const PATTERN_POSITIVE = ['yes', '1', true, 1, 'true'];
	public const PATTERN_NEGATIVE = ['no', '0', false, 0, 'false'];

	/**
	 * @param mixed $value
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		$found = in_array($value, self::PATTERN_POSITIVE, true);
		return json_encode($found ? 'true' : 'false');
	}

	/**
	 * @param mixed $value
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		if($value === null) {
			return true;
		}

		return in_array($value, self::PATTERN_POSITIVE) || in_array($value, self::PATTERN_NEGATIVE) ;
	}

}
