<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;
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
	 * @param list<array{columnId: int, mode: 'ASC'|'DESC'}> $data
	 * @throws InvalidArgumentException
	 */
	public static function createFromInputArray(array $data): self {
		$sortRules = [];
		foreach ($data as $inputSortRule) {
			if (!isset($inputSortRule['columnId'], $inputSortRule['mode'])) {
				throw new InvalidArgumentException('Required sort parameters are missing');
			}

			$sortRules[] = new SortRule(
				columnId: (int)$inputSortRule['columnId'],
				mode: (string)$inputSortRule['mode']
			);
		}
		return new self($sortRules);
	}

	public function jsonSerialize(): array {
		return array_map(static fn (SortRule $s) => $s->jsonSerialize(), $this->sortRules);
	}
}
