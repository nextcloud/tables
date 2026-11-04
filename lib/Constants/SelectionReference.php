<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Constants;

final class SelectionReference {
	public const PREFIX = '@selection-id-';

	public static function isReference(mixed $value): bool {
		return is_string($value) && str_starts_with($value, self::PREFIX);
	}

	public static function optionId(mixed $value): ?int {
		if (!self::isReference($value)) {
			return null;
		}
		$id = substr((string)$value, strlen(self::PREFIX));

		return $id === '' ? null : (int)$id;
	}

	public static function forOptionId(int $optionId): string {
		return self::PREFIX . $optionId;
	}
}
