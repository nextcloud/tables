<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;
use OCA\Tables\Db\Column;
use OCA\Tables\Service\ValueObject\SortRule;

class SortRuleSet implements JsonSerializable {
	/**
	 * @param SortRule[] $sortRules
	 * @throws InvalidArgumentException
	 */
	public function __construct(
		protected array $sortRules,
	) {
		foreach ($this->sortRules as $sortRule) {
			if (!($sortRule instanceof SortRule)) {
				throw new InvalidArgumentException('Provided sort rule must be an instance of SortRule');
			}
		}
	}

	/**
	 * @param list<array{columnId: int, columnUuid?: string, mode: 'ASC'|'DESC'}> $data
	 * @throws InvalidArgumentException
	 */
	public static function createFromInputArray(array $data, array $columnsMap = []): self {
		$sortRules = [];
		foreach ($data as $inputSortRule) {
			if (!is_array($inputSortRule)) {
				throw new InvalidArgumentException('Each sort rule entry must be an array');
			}

			// Resolve columnId from uuid if provided
			if (isset($inputSortRule['columnUuid']) && isset($columnsMap[$inputSortRule['columnUuid']]) && $columnsMap[$inputSortRule['columnUuid']] instanceof Column) {
				$inputSortRule['columnId'] = $columnsMap[$inputSortRule['columnUuid']]->getId();
			}

			if (!isset($inputSortRule['columnId']) && !empty($columnsMap)) {
				continue; // Skip sort rules that reference a column that doesn't exist in the current table
			}

			if (!isset($inputSortRule['columnId'], $inputSortRule['mode'])) {
				throw new InvalidArgumentException('Required sort parameters are missing');
			}
			self::assertColumnIdInBounds($inputSortRule['columnId']);

			$sortRules[] = new SortRule(
				columnId: (int)$inputSortRule['columnId'],
				mode: (string)$inputSortRule['mode']
			);
		}
		return new self($sortRules);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private static function assertColumnIdInBounds(mixed $columnId): void {
		$maxDigits = strlen((string)PHP_INT_MAX);
		if (!is_numeric($columnId)
			|| (int)$columnId < -5
			|| !preg_match('/^-?\\d{0,' . $maxDigits . '}$/', (string)$columnId)
		) {
			throw new InvalidArgumentException(sprintf('Invalid column id supplied: %s', (string)$columnId));
		}
	}

	/**
	 * @return list<array{columnId: int, mode: 'ASC'|'DESC'}>
	 */
	public function jsonSerialize(): array {
		return array_map(static fn (SortRule $s) => $s->jsonSerialize(), $this->sortRules);
	}
}
