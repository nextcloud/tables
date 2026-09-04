<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonException;

final class FilterInput {

	/**
	 * @param list<list<array{columnId: int|string, operator: string, value: mixed}>> $filter
	 */
	private function __construct(
		public readonly array $filter,
	) {
	}

	public static function fromRequestValue(string $value): self {
		if ($value === null || $value === '') {
			return new self([]);
		}

		if (is_string($value)) {
			try {
				$value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				throw new InvalidArgumentException('Invalid filter supplied', 0, $e);
			}
		}

		if ($value === null) {
			return new self([]);
		}

		if (!is_array($value)) {
			throw new InvalidArgumentException('Invalid filter supplied');
		}

		return new self($value);
	}
}
