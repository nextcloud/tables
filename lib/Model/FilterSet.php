<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;

class FilterSet implements JsonSerializable {

	/**
	 * @param FilterGroup[] $filterGroups
	 */
	public function __construct(
		protected array $filterGroups,
	) {
		foreach ($this->filterGroups as $filterGroup) {
			if (!($filterGroup instanceof FilterGroup)) {
				throw new InvalidArgumentException('Provided filterGroup must be an instance of FilterGroup');
			}
		}
	}

	public static function createFromInputArray(array $data): self {
		$filterGroups = [];
		foreach ($data as $inputFilterGroup) {
			$filterGroups[] = FilterGroup::createFromInputArray($inputFilterGroup);
		}
		return new self($filterGroups);
	}

	public function jsonSerialize(): array {
		return array_map(static fn (FilterGroup $fg) => $fg->jsonSerialize(), $this->filterGroups);
	}
}
