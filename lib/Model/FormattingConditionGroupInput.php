<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use OCA\Tables\Constants\FilterOperator;

class FormattingConditionGroupInput {
	use FormattingInputCast;

	private const MAX_COLUMN_TYPE_LENGTH = 64;
	private const MAX_CONDITIONS = 20;

	private const MAX_VALUE_LENGTH = 1024;

	private const VALUELESS_OPERATORS = [
		FilterOperator::IS_EMPTY,
		FilterOperator::IS_NOT_EMPTY,
	];

	/** @param list<array{columnId: int, columnType: string, operator: string, value?: scalar|null|list<mixed>}> $conditions */
	private function __construct(
		private readonly array $conditions,
	) {
	}

	public static function createFromInputArray(array $data): self {
		if (!isset($data['conditions']) || !is_array($data['conditions'])) {
			throw new InvalidArgumentException('conditions must be an array');
		}
		if (count($data['conditions']) > self::MAX_CONDITIONS) {
			throw new InvalidArgumentException('Max ' . self::MAX_CONDITIONS . ' conditions per group');
		}

		$conditions = [];
		foreach ($data['conditions'] as $raw) {
			if (!is_array($raw)) {
				throw new InvalidArgumentException('Each condition must be an array');
			}
			if (!isset($raw['columnId'], $raw['columnType'], $raw['operator'])) {
				throw new InvalidArgumentException('Condition requires columnId, columnType and operator');
			}

			$operator = FilterOperator::tryFrom(self::castString($raw['operator'], 'operator'));
			if ($operator === null) {
				throw new InvalidArgumentException('Unknown operator: ' . $raw['operator']);
			}

			$needsValue = !in_array($operator, self::VALUELESS_OPERATORS, true);
			if ($needsValue && !array_key_exists('value', $raw)) {
				throw new InvalidArgumentException('Operator ' . $operator->value . ' requires a value');
			}

			$condition = [
				'columnId' => self::castInt($raw['columnId'], 'columnId'),
				'columnType' => self::castBoundedString($raw['columnType'], 'columnType', self::MAX_COLUMN_TYPE_LENGTH),
				'operator' => $operator->value,
			];
			if ($needsValue) {
				$condition['value'] = self::assertValue($raw['value']);
			}
			$conditions[] = $condition;
		}

		return new self($conditions);
	}

	/**
	 * @return scalar|null|list<mixed>
	 * @throws InvalidArgumentException
	 */
	private static function assertValue(mixed $value): mixed {
		if (!is_array($value)) {
			return self::assertScalarValue($value, 'value');
		}

		$entries = [];
		foreach (array_values($value) as $index => $entry) {
			if (!is_array($entry)) {
				$entries[] = self::assertScalarValue($entry, 'value[' . $index . ']');
				continue;
			}
			$option = [];
			foreach ($entry as $key => $property) {
				$option[$key] = self::assertScalarValue($property, 'value[' . $index . '][' . $key . ']');
			}
			$entries[] = $option;
		}

		return $entries;
	}

	/** @throws InvalidArgumentException */
	private static function assertScalarValue(mixed $value, string $field): mixed {
		if ($value !== null && !is_scalar($value)) {
			throw new InvalidArgumentException($field . ' must be a scalar');
		}
		if (is_string($value) && mb_strlen($value) > self::MAX_VALUE_LENGTH) {
			throw new InvalidArgumentException($field . ' must be at most ' . self::MAX_VALUE_LENGTH . ' characters');
		}
		return $value;
	}

	public function toArray(): array {
		return ['conditions' => $this->conditions];
	}

	/** @return int[] */
	public function collectColumnIds(): array {
		return array_column($this->conditions, 'columnId');
	}
}
