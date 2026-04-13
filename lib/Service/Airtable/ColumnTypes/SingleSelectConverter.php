<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: singleSelect
 *
 * Airtable choices are `{id: "selXXXX", name: "Label", color: "…"}`.
 * We assign sequential integer IDs (1-based) to preserve order, matching
 * the Tables selection-options format `[{"id": 1, "label": "Label"}, …]`.
 */
class SingleSelectConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'singleSelect';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$options = $this->buildOptions($rawAirtableColumn);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'selection',
			subtype: 'single',
			selectionOptions: json_encode(array_values($options)),
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		$label = (string) $rawValue;
		foreach ($this->buildOptions($rawAirtableColumn) as $option) {
			if ($option['label'] === $label) {
				return $option['id'];
			}
		}
		return null;
	}

	/**
	 * Build Tables-format options array from Airtable typeOptions.choices.
	 *
	 * @return list<array{id: int, label: string}>
	 */
	private function buildOptions(array $rawAirtableColumn): array {
		$choices = $rawAirtableColumn['typeOptions']['choices']
			?? $rawAirtableColumn['options']['choices']
			?? [];

		$options = [];
		$id = 1;
		foreach ($choices as $choice) {
			$label = (string) ($choice['name'] ?? $choice['label'] ?? '');
			if ($label !== '') {
				$options[] = ['id' => $id++, 'label' => $label];
			}
		}
		return $options;
	}
}
