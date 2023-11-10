<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class NumberStarsBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function parseValue(?string $value, ?Column $column = null): string {
		return json_encode((int) $value);
	}

	public function canBeParsed(?string $value, ?Column $column = null): bool {
		return !$value || in_array((int) $value, array(0,1,2,3,4,5));
	}

}
