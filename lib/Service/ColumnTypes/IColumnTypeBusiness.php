<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

interface IColumnTypeBusiness {

	/*
	 * try to parse a string for the given column type
	 *
	 * @return value stringify
	 */
	public function parseValue(string $value, ?Column $column): string;

	/*
	 * tests if the given string can be parsed to a value of the column type
	 *
	 * @return value stringify
	 */
	public function canBeParsed(string $value, ?Column $column): bool;
}
