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
	private const MAX_CONDITIONS = 20;

	/** Operators that match on the cell being set or unset and therefore carry no value. */
	private const VALUELESS_OPERATORS = [
		FilterOperator::IS_EMPTY,
		FilterOperator::IS_NOT_EMPTY,
	];

	/** @param list<array{columnId: int, columnType: string, operator: string, value?: scalar|list<mixed>}> $conditions */
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

			$operator = FilterOperator::tryFrom((string)$raw['operator']);
			if ($operator === null) {
				throw new InvalidArgumentException('Unknown operator: ' . $raw['operator']);
			}

			$needsValue = !in_array($operator, self::VALUELESS_OPERATORS, true);
			if ($needsValue && !array_key_exists('value', $raw)) {
				throw new InvalidArgumentException('Operator ' . $operator->value . ' requires a value');
			}

			$condition = [
				'columnId' => (int)$raw['columnId'],
				'columnType' => (string)$raw['columnType'],
				'operator' => $operator->value,
			];
			if ($needsValue) {
				$condition['value'] = is_array($raw['value']) ? array_values($raw['value']) : $raw['value'];
			}
			$conditions[] = $condition;
		}

		return new self($conditions);
	}

	public function toArray(): array {
		return ['conditions' => $this->conditions];
	}

	/** @return int[] */
	public function collectColumnIds(): array {
		return array_column($this->conditions, 'columnId');
	}
}
