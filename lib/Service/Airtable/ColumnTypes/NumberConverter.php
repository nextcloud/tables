<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/** Handles Airtable field type: number (integer and decimal variants) */
class NumberConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'number';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$decimals = (int) ($rawAirtableColumn['typeOptions']['precision'] ?? 0);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'number',
			numberDecimals: $decimals > 0 ? $decimals : null,
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		return is_int($rawValue) ? $rawValue : (float) $rawValue;
	}
}
