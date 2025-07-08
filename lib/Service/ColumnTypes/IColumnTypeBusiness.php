<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use OCA\Tables\Errors\BadRequestError;

interface IColumnTypeBusiness {

	/**
	 * Parse frontend value and transform for using it in the database
	 *
	 * Used when inserting from API to the database
	 *
	 * FIXME: Why is this not using Mapper methods which should do the same thing
	 *
	 * @param mixed $value
	 * @param Column|null $column
	 * @return string value stringify
	 */
	public function parseValue($value, ?Column $column): string;

	/**
	 * tests if the given value can be parsed to a value of the column type
	 *
	 * @param mixed $value
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column): bool;

	/**
	 * @throws BadRequestError In case the value is not valid
	 *
	 * @param mixed $value
	 * @param Column $column
	 * @param int|null $rowId
	 */
	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void;

	/**
	 * tests if the given string can be parsed to a value/id of the column type
	 *
	 * @param mixed $value
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsedDisplayValue($value, ?Column $column): bool;

	/**
	 * parses the given string to a value/id of the column type
	 *
	 * @param mixed $value
	 * @param Column|null $column
	 * @return string
	 */
	public function parseDisplayValue($value, ?Column $column): string;
}
