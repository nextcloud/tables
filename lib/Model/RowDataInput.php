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

	/**
	 * @param array|float|int|null|string $value
	 */
	public function add(int $columnId, array|string|int|float|null $value): self {
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

	public static function createFromInputArray(array $cells): self {
		if (!array_is_list($cells)) {
			throw new \InvalidArgumentException('Row data must be a list of cells.');
		}
		$input = new self();
		foreach ($cells as $cell) {
			if (!is_array($cell) || !array_key_exists('columnId', $cell) || !array_key_exists('value', $cell)) {
				throw new \InvalidArgumentException('Each cell requires columnId and value.');
			}
			$columnId = $cell['columnId'];
			if (is_string($columnId) && preg_match('/^-?[0-9]+$/D', $columnId)) {
				$digits = ltrim(ltrim($columnId, '-'), '0');
				$canonical = ($digits !== '' && str_starts_with($columnId, '-') ? '-' : '') . ($digits === '' ? '0' : $digits);
				$columnId = filter_var($canonical, FILTER_VALIDATE_INT);
			}
			if (!is_int($columnId)) {
				throw new \InvalidArgumentException('Cell columnId must be an integer within the supported range.');
			}
			$value = $cell['value'];
			if ($value !== null && !is_scalar($value) && !is_array($value)) {
				throw new \InvalidArgumentException('Cell values must be JSON values.');
			}
			$input->add($columnId, is_bool($value) ? (int)$value : $value);
		}
		return $input;
	}

	public static function fromArray(array $data): self {
		$newRowData = new RowDataInput();
		foreach ($data as $value) {
			$newRowData->add((int)$value['columnId'], $value['value']);
		}
		return $newRowData;
	}
}
