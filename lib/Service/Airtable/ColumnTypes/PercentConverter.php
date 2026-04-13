<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: percent
 *
 * Airtable stores percent values as decimal fractions (0.5 = 50 %).
 * We multiply by 100 so the stored number matches the displayed percentage.
 */
class PercentConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'percent';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$decimals = (int) ($rawAirtableColumn['typeOptions']['precision'] ?? 0);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'number',
			numberDecimals: $decimals > 0 ? $decimals : null,
			numberSuffix: '%',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		// Airtable stores 0.5 for 50 %; convert to 50 for display with the % suffix.
		return round((float) $rawValue * 100, 8);
	}
}
