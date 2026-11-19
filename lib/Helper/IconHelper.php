<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Helper;

use OCA\Tables\Errors\BadRequestError;

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

	/**
	 * An optional named icon, trimmed, or null for none or an empty one.
	 *
	 * @param string $of what the icon belongs to, such as "a menu item" or "widget 2"
	 * @throws BadRequestError
	 */
	public static function optional(mixed $icon, string $of): ?string {
		if ($icon === null) {
			return null;
		}
		$icon = is_string($icon) ? trim($icon) : $icon;
		if ($icon === '') {
			return null;
		}
		if (!is_string($icon) || !self::isValid($icon)) {
			throw new BadRequestError('The icon of ' . $of . ' must be the name of an icon.');
		}
		return $icon;
	}
}
