<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;
use OCA\Tables\Db\Column;

class ViewSettings implements JsonSerializable {
	public function __construct(
		protected readonly ?int $cardBackgroundSource = null,
		protected readonly ?int $cardTitleSource = null,
	) {
	}

	/**
	 * @param array{cardBackgroundSource?: int|null, cardTitleSource?: int|null, cardBackgroundSourceUuid?: string, cardTitleSourceUuid?: string} $data
	 * @param array<string, Column> $columnsMap
	 */
	public static function createFromInputArray(array $data, array $columnsMap = []): self {
		return new self(
			cardBackgroundSource: self::resolveSource($data, 'cardBackgroundSource', $columnsMap),
			cardTitleSource: self::resolveSource($data, 'cardTitleSource', $columnsMap),
		);
	}

	/**
	 * A scheme carries the column uuid, because the ids of the table it was exported from mean
	 * nothing here. A source that no longer resolves is dropped rather than kept, as a stale id
	 * would otherwise point at an unrelated column.
	 *
	 * @param array<string, Column> $columnsMap
	 */
	private static function resolveSource(array $data, string $key, array $columnsMap): ?int {
		$uuidKey = $key . 'Uuid';
		if (array_key_exists($uuidKey, $data)) {
			$uuid = $data[$uuidKey];
			if (!is_string($uuid) || !isset($columnsMap[$uuid])) {
				return null;
			}
			return $columnsMap[$uuid]->getId();
		}

		return self::nullableIntFromArray($data, $key);
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
