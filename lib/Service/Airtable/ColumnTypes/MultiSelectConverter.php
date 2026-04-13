<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: multiSelect
 *
 * Uses the same sequential integer ID assignment as SingleSelectConverter.
 * Cell values are returned as an array of integer option IDs, which
 * SelectionMultiBusiness::parseValue() accepts directly.
 */
class MultiSelectConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'multiSelect';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$options = $this->buildOptions($rawAirtableColumn);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'selection',
			subtype: 'multi',
			selectionOptions: json_encode(array_values($options)),
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === [] || $rawValue === '') {
			return null;
		}

		$labels  = is_array($rawValue) ? $rawValue : [$rawValue];
		$options = $this->buildOptions($rawAirtableColumn);
		$labelToId = [];
		foreach ($options as $option) {
			$labelToId[$option['label']] = $option['id'];
		}

		$ids = [];
		foreach ($labels as $label) {
			$label = (string) $label;
			if (isset($labelToId[$label])) {
				$ids[] = $labelToId[$label];
			}
		}

		return $ids !== [] ? $ids : null;
	}

	/**
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
