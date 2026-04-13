<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/** Handles Airtable field type: currency */
class CurrencyConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'currency';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$symbol   = (string) ($rawAirtableColumn['typeOptions']['symbol'] ?? '');
		$decimals = (int) ($rawAirtableColumn['typeOptions']['precision'] ?? 2);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'number',
			numberDecimals: $decimals,
			numberPrefix: $symbol !== '' ? $symbol : null,
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		return (float) $rawValue;
	}
}
