<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

/**
 * Named icons: the material design icons shipped in `img/material`.
 */
class IconHelper {
	public static function isValid(string $iconName): bool {
		if ($iconName === '' || !preg_match('/^[a-zA-Z0-9-]+$/', $iconName)) {
			return false;
		}
		return file_exists(dirname(__DIR__, 2) . '/img/material/' . $iconName . '.svg');
	}
}
