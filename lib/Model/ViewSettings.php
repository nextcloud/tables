<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;

class ViewSettings implements JsonSerializable {
	public function __construct(
		protected readonly ?int $cardBackgroundSource = null,
		protected readonly ?int $cardTitleSource = null,
	) {
	}

	/**
	 * @param array{cardBackgroundSource?: int|null, cardTitleSource?: int|null} $data
	 */
	public static function createFromInputArray(array $data): self {
		return new self(
			cardBackgroundSource: self::nullableIntFromArray($data, 'cardBackgroundSource'),
			cardTitleSource: self::nullableIntFromArray($data, 'cardTitleSource'),
		);
	}

	public function getCardBackgroundSource(): ?int {
		return $this->cardBackgroundSource;
	}

	public function getCardTitleSource(): ?int {
		return $this->cardTitleSource;
	}

	/**
	 * @return array{cardBackgroundSource: int|null, cardTitleSource: int|null}
	 */
	public function jsonSerialize(): array {
		return [
			'cardBackgroundSource' => $this->cardBackgroundSource,
			'cardTitleSource' => $this->cardTitleSource,
		];
	}

	private static function nullableIntFromArray(array $data, string $key): ?int {
		if (!array_key_exists($key, $data) || $data[$key] === null) {
			return null;
		}

		if (!is_int($data[$key])) {
			throw new InvalidArgumentException('Invalid ' . $key . ' value.');
		}

		return $data[$key];
	}
}
