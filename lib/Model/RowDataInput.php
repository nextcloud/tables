<?php

namespace OCA\Tables\Model;

use ArrayAccess;

/**
 * @template-implements ArrayAccess<mixed, array{'columnId': int, 'value': mixed}>
 */
class RowDataInput implements ArrayAccess {
	protected const DATA_KEY = 'columnId';
	protected const DATA_VAL = 'value';
	/** @psalm-var array<array{'columnId': int, 'value': mixed}> */
	protected array $data = [];

	public function add(int $columnId, string $value): self {
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
}
