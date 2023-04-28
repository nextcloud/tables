<?php

namespace OCA\Tables\Service\ColumnTypes;

interface IColumnTypeBusiness {

	/*
	 * try to parse a string for the given column type
	 *
	 * @return value stringify
	 */
	public function parseValue(string $value): string;
}
