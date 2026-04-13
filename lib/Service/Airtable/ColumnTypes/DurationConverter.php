<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: duration (lossy)
 *
 * Airtable stores duration values as an integer number of seconds.
 * Loss: mapped to a plain number column — no [h]:mm:ss rendering.
 * Resolved in Phase 3 when the number/duration subtype is implemented (P3.3).
 */
class DurationConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'duration';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			'duration',
			'Imported as a number (seconds). The [h]:mm:ss display format is not yet supported (Phase 3).',
		);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'number',
			numberSuffix: 's',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		// Airtable duration values are integers (seconds).
		return (int) $rawValue;
	}
}
