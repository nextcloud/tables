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
	public const SOURCE_KEYS = ['cardBackgroundSource', 'cardTitleSource'];

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

	/**
	 * Card sources rewritten through a map of old to new column id.
	 *
	 * A source that does not resolve is dropped rather than carried over: the same id
	 * addresses a different column once the data has moved, so keeping it would point
	 * the card at unrelated content.
	 *
	 * @param array<int, int> $columnIdMap
	 */
	public static function remapSources(array $viewSettings, array $columnIdMap): array {
		foreach (self::SOURCE_KEYS as $sourceKey) {
			if (!array_key_exists($sourceKey, $viewSettings) || $viewSettings[$sourceKey] === null) {
				continue;
			}

			$sourceId = $viewSettings[$sourceKey];
			$viewSettings[$sourceKey] = is_int($sourceId) && $sourceId > 0
				? ($columnIdMap[$sourceId] ?? null)
				: null;
		}

		return $viewSettings;
	}
}
