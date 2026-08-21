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

	public static function createFromInputArray(array $data, array $columnsMap = []): self {
		$filterGroups = [];
		foreach ($data as $inputFilterGroup) {
			foreach ($inputFilterGroup as $j => $item) {
				if (isset($columnsMap[$item['columnUuid']]) && $columnsMap[$item['columnUuid']] instanceof Column) {
					$inputFilterGroup[$j]['columnId'] = $columnsMap[$item['columnUuid']]->getId();
				}
				if (!isset($inputFilterGroup[$j]['columnId']) && !empty($columnsMap)) {
					unset($inputFilterGroup[$j]); // Remove the item if the column doesn't exist
				}
			}
			if (empty($inputFilterGroup)) {
				continue; // Skip empty filter groups
			}
			$filterGroups[] = FilterGroup::createFromInputArray($inputFilterGroup);
		}
		return new self($filterGroups);
	}

	public function jsonSerialize(): array {
		return array_map(static fn (FilterGroup $fg) => $fg->jsonSerialize(), $this->filterGroups);
	}
}
