<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Validation;

/**
 * The rule for technical names of columns, views, pages and menu items: lower case
 * letters, digits and underscores, starting with a letter, at most 200 characters.
 * Callers report a broken rule in their own words.
 */
class TechnicalNameValidator {
	public const MAX_LENGTH = 200;
	private const PATTERN = '/^[a-z][a-z0-9_]*$/D';

	public static function isValid(string $technicalName): bool {
		return !self::isTooLong($technicalName) && self::hasValidCharacters($technicalName);
	}

	public static function isTooLong(string $technicalName): bool {
		return strlen($technicalName) > self::MAX_LENGTH;
	}

	public static function hasValidCharacters(string $technicalName): bool {
		return preg_match(self::PATTERN, $technicalName) === 1;
	}
}
