<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;

class FormattingConditionGroupInput {
	private const VALID_OPERATORS = [
		'eq', 'neq', 'gt', 'lt', 'gte', 'lte', 'between',
		'contains', 'startsWith', 'isEmpty', 'isNotEmpty',
		'in', 'before', 'after', 'isToday', 'isThisWeek',
		'isTrue', 'isFalse',
	];

	private const MAX_CONDITIONS = 20;

	/** @param list<array{columnId: int, columnType: string, operator: string}> $conditions */
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
			if (!in_array((string)$raw['operator'], self::VALID_OPERATORS, true)) {
				throw new InvalidArgumentException('Unknown operator: ' . $raw['operator']);
			}

			$condition = [
				'columnId' => (int)$raw['columnId'],
				'columnType' => (string)$raw['columnType'],
				'operator' => (string)$raw['operator'],
			];
			if (array_key_exists('value', $raw)) {
				$condition['value'] = $raw['value'];
			}
			if (array_key_exists('values', $raw) && is_array($raw['values'])) {
				$condition['values'] = array_values($raw['values']);
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
