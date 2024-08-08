<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class NumberBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @param mixed $value (int|float|string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		if ($value === null) {
			return '';
		}
		return json_encode(floatval($value));
	}


	/**
	 * @param mixed $value (int|float|string|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		return !$value || floatval($value);
	}

}
