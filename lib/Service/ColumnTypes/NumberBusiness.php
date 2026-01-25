<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class NumberBusiness extends SuperBusiness {

	/**
	 * @param mixed $value (int|float|string|null)
	 * @param Column $column
	 * @return string
	 */
	public function parseValue($value, Column $column): string {
		if ($value === null) {
			return '';
		}
		return json_encode(floatval($value));
	}


	/**
	 * @param mixed $value (int|float|string|null)
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsed($value, Column $column): bool {
		return !$value || floatval($value);
	}

}
