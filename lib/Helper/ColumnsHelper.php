<?php

namespace OCA\Tables\Helper;

class ColumnsHelper {

	public array $columns = [
		'text',
		'number',
		'datetime',
		'selection'
	];

	public function isSupportedColumnType(string $type): bool {
		return in_array($type, $this->columns, true);
	}

}
