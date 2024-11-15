<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class DatetimeTimeBusiness extends SuperBusiness implements IColumnTypeBusiness {

	/**
	 * @param mixed $value (string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		return json_encode($this->isValidDate((string)$value, 'H:i') ? $value : '');
	}

	/**
	 * @param mixed $value (string|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		return $this->isValidDate((string)$value, 'H:i');
	}

}
