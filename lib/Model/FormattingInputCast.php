<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

trait FormattingInputCast {
	/** @throws InvalidArgumentException */
	private static function castString(mixed $value, string $field): string {
		if (is_string($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return (string)$value;
		}
		throw new InvalidArgumentException($field . ' must be a string');
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private static function castInt(mixed $value, string $field): int {
		if (is_int($value)) {
			return $value;
		}
		if (is_string($value) && preg_match('/^-?\d+\z/', $value) === 1 && (string)(int)$value === $value) {
			return (int)$value;
		}
		throw new InvalidArgumentException($field . ' must be an integer');
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private static function castBool(mixed $value, string $field): bool {
		if (is_bool($value)) {
			return $value;
		}
		if ($value === 1 || $value === 0 || $value === '1' || $value === '0' || $value === 'true' || $value === 'false') {
			return $value === 1 || $value === '1' || $value === 'true';
		}
		throw new InvalidArgumentException($field . ' must be a boolean');
	}

	/** @throws InvalidArgumentException */
	private static function castBoundedString(mixed $value, string $field, int $maxLength): string {
		$string = self::castString($value, $field);
		if (mb_strlen($string) > $maxLength) {
			throw new InvalidArgumentException($field . ' must be at most ' . $maxLength . ' characters');
		}
		return $string;
	}
}
