<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

class ColumnsHelper {

	public array $columns = [
		'text',
		'number',
		'datetime',
		'selection',
		'usergroup'
	];
	public function isSupportedColumnType(string $type): bool {
		return in_array($type, $this->columns, true);
	}

}
