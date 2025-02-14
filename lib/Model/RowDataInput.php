<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use ArrayAccess;
use Iterator;
use function current;
use function key;
use function next;
use function reset;

/**
 * @template-implements ArrayAccess<mixed, array{'columnId': int, 'value': mixed}>
 * @template-implements Iterator<mixed, array{'columnId': int, 'value': mixed}>
 */
class RowDataInput implements ArrayAccess, Iterator {
	protected const DATA_KEY = 'columnId';
	protected const DATA_VAL = 'value';
	/** @psalm-var array<array{'columnId': int, 'value': mixed}> */
	protected array $data = [];

	public function add(int $columnId, mixed $value): self {
		$this->data[] = [self::DATA_KEY => $columnId, self::DATA_VAL => $value];
		return $this;
	}

	public function offsetExists(mixed $offset): bool {
		foreach ($this->data as $data) {
			if ($data[self::DATA_KEY] === $offset[self::DATA_KEY]) {
				return true;
			}
		}
		return false;
	}

	public function offsetGet(mixed $offset): mixed {
		return $this->data[$offset];
	}

	public function offsetSet(mixed $offset, mixed $value): void {
		$this->data[$offset] = $value;
	}

	public function offsetUnset(mixed $offset): void {
		if (isset($this->data[$offset])) {
			unset($this->data[$offset]);
		}
	}

	public function hasColumn(int $columnId): bool {
		foreach ($this->data as $data) {
			if ($data[self::DATA_KEY] === $columnId) {
				return true;
			}
		}
		return false;
	}

	public function current(): mixed {
		return current($this->data);
	}

	public function next(): void {
		next($this->data);
	}

	public function key(): mixed {
		return key($this->data);
	}

	public function valid(): bool {
		return $this->key() !== null;
	}

	public function rewind(): void {
		reset($this->data);
	}

	public static function fromArray(array $data): self {
		$newRowData = new RowDataInput();
		foreach ($data as $value) {
			$newRowData->add((int)$value['columnId'], $value['value']);
		}
		return $newRowData;
	}
}
