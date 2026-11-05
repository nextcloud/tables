<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use OCA\Tables\Vendor\Symfony\Component\Uid\UuidV7;

final class RowIdentifier {
	/** Highest value of the signed 32-bit tables_row_sleeves.id column. */
	private const MAX_ROW_ID = 2_147_483_647;

	private function __construct(
		public readonly int|string $value,
	) {
	}

	public static function fromString(string $value): self {
		if (preg_match('/^[0-9]+$/D', $value)) {
			$digits = ltrim($value, '0');
			$maximum = (string)self::MAX_ROW_ID;
			if ($digits === '' || strlen($digits) > strlen($maximum)
				|| (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
				throw new \InvalidArgumentException('Row ID must be a positive integer within the supported range.');
			}
			return new self((int)$digits);
		}

		try {
			return new self((new UuidV7($value))->toRfc4122());
		} catch (\InvalidArgumentException $e) {
			throw new \InvalidArgumentException('Row identifier must be a positive integer or UUIDv7.', 0, $e);
		}
	}

	public static function tryFromInt(int $rowId): ?self {
		return $rowId > 0 && $rowId <= self::MAX_ROW_ID ? new self($rowId) : null;
	}

	public function isUuid(): bool {
		return is_string($this->value);
	}
}
