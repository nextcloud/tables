<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class NumberStarsBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @param mixed $value (null|int|string)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		return json_encode((int) $value);
	}

	/**
	 * @param mixed $value (null|int|string)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		return !$value || in_array((int) $value, array(0,1,2,3,4,5));
	}

}
