<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/** Handles Airtable field type: rating */
class RatingConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'rating';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'number',
			subtype: 'stars',
			numberMin: 0,
			numberMax: (float) ($rawAirtableColumn['typeOptions']['max'] ?? 5),
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		return (int) $rawValue;
	}
}
