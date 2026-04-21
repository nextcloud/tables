<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

use ArrayAccess;
use JsonSerializable;

/**
 * Value object representing the column id and display order for a table.
 * This is the base type for table column ordering; ViewColumnInformation
 * extends it with view-specific fields (readonly, mandatory).
 *
 * @template-implements ArrayAccess<string, bool|int>
 */
class ColumnOrderInformation implements ArrayAccess, JsonSerializable {
	public const KEY_ID = 'columnId';
	public const KEY_ORDER = 'order';

	protected array $data = [];
	protected const KEYS = [
		self::KEY_ID,
		self::KEY_ORDER,
	];

	public function __construct(int $columnId, int $order) {
		$this->offsetSet(self::KEY_ID, $columnId);
		$this->offsetSet(self::KEY_ORDER, $order);
	}

	public function getId(): int {
		return (int)$this->offsetGet(self::KEY_ID);
	}

	public function getOrder(): int {
		return (int)$this->offsetGet(self::KEY_ORDER);
	}

	public static function fromArray(array $data): self {
		if (!isset($data[self::KEY_ID], $data[self::KEY_ORDER])) {
			throw new \InvalidArgumentException('Column settings entry is missing required fields: columnId and order are required');
		}
		return new self((int)$data[self::KEY_ID], (int)$data[self::KEY_ORDER]);
	}

	public function offsetExists(mixed $offset): bool {
		return in_array((string)$offset, static::KEYS);
	}

	public function offsetGet(mixed $offset): bool|int {
		return $this->data[$offset];
	}

	public function offsetSet(mixed $offset, mixed $value): void {
		if (!$this->offsetExists($offset)) {
			return;
		}
		$this->data[$offset] = $this->ensureType((string)$offset, $value);
	}

	public function offsetUnset(mixed $offset): void {
		if (!$this->offsetExists($offset)) {
			return;
		}
		unset($this->data[(string)$offset]);
	}

	/**
	 * @return array{columnId: int, order: int, ...}
	 */
	public function jsonSerialize(): array {
		return [
			self::KEY_ID => $this->getId(),
			self::KEY_ORDER => $this->getOrder(),
		];
	}

	protected function ensureType(string $offset, mixed $value): int|bool {
		return match ($offset) {
			self::KEY_ID,
			self::KEY_ORDER => (int)$value,
			default => throw new \InvalidArgumentException("Invalid offset: $offset"),
		};
	}
}
