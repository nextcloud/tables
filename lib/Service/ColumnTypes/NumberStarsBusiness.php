<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class NumberStarsBusiness extends SuperBusiness {

	/**
	 * @param mixed $value (null|int|string)
	 * @param Column $column
	 * @return string
	 */
	public function parseValue($value, Column $column): string {
		return json_encode((int)$value);
	}

	/**
	 * @param mixed $value (null|int|string)
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsed($value, Column $column): bool {
		return !$value || in_array((int)$value, [0,1,2,3,4,5]);
	}

}
