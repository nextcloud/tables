<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class DatetimeDateBusiness extends SuperBusiness {

	/**
	 * @param mixed $value (string|null)
	 * @param Column $column
	 *
	 * @return false|string
	 */
	public function parseValue($value, Column $column): string|false {
		return json_encode($this->isValidDate((string)$value, 'Y-m-d') ? (string)$value : '');
	}

	/**
	 * @param mixed $value (string|null)
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsed($value, Column $column): bool {
		return $this->isValidDate((string)$value, 'Y-m-d');
	}
}
