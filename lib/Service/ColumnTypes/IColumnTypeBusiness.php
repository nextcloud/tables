<?php

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

interface IColumnTypeBusiness {

	/**
	 * try to parse a string for the given column type
	 *
	 * @param mixed $value
	 * @param Column|null $column
	 * @return string value stringify
	 */
	public function parseValue($value, ?Column $column): string;

	/**
	 * tests if the given string can be parsed to a value of the column type
	 *
	 * @param mixed $value
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column): bool;
}
