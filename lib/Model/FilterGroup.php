<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;
use OCA\Tables\Constants\FilterOperator;
use OCA\Tables\Service\ValueObject\Filter;
use ValueError;

class FilterGroup implements JsonSerializable {

	/**
	 * @param Filter[] $filters
	 */
	public function __construct(
		protected array $filters,
	) {
		foreach ($filters as $filter) {
			if (!$filter instanceof Filter) {
				throw new InvalidArgumentException('Provided filter must be an instance of Filter');
			}
		}
	}

	public static function createFromInputArray(array $data): self {
		$filters = [];
		foreach ($data as $filterInput) {
			if (!isset($filterInput['columnId'], $filterInput['operator'], $filterInput['value'])) {
				throw new InvalidArgumentException('Required input fields are missing');
			}
			try {
				$filters[] = new Filter(
					(int)$filterInput['columnId'],
					FilterOperator::from($filterInput['operator']),
					$filterInput['value'],
				);
			} catch (ValueError $e) {
				throw new InvalidArgumentException('Invalid input data passed to Filter', 0, $e);
			}
		}
		return new self($filters);
	}

	public function jsonSerialize(): array {
		return array_map(static fn (Filter $f) => $f->jsonSerialize(), $this->filters);
	}
}
