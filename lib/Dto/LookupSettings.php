<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Dto;

readonly class LookupSettings {
	public function __construct(
		public int $relationColumnId,
		public int $targetColumnId,
	) {
		if ($relationColumnId <= 0 || $targetColumnId <= 0) {
			throw new \InvalidArgumentException('Lookup relation and target column IDs must be positive integers.');
		}
	}

	public static function fromArray(array $data): self {
		return new self(
			relationColumnId: self::parseIntField($data['relationColumnId'] ?? null, 'relationColumnId'),
			targetColumnId: self::parseIntField($data['targetColumnId'] ?? null, 'targetColumnId'),
		);
	}

	/**
	 * Settings may arrive as form parameters, where integers are encoded as strings.
	 */
	private static function parseIntField(mixed $value, string $field): int {
		if (is_string($value) && ctype_digit($value)) {
			$value = (int)$value;
		}
		if (!is_int($value)) {
			throw new \InvalidArgumentException($field . ' must be an integer.');
		}
		return $value;
	}
}
