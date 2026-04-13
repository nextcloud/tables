<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/** Handles Airtable field type: dateTime */
class DateTimeConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'dateTime';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'datetime',
			subtype: 'datetime',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		// Airtable datetime values are ISO 8601 strings: "2023-01-15T10:30:00.000Z".
		return (string) $rawValue;
	}
}
