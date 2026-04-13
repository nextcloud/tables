<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: autoNumber (lossy — column skipped)
 *
 * Loss: Nextcloud Tables rows have their own auto-incrementing ID; there is
 * no mechanism to preserve Airtable's original autonumber values in a
 * dedicated column without potentially confusing them with the row ID.
 * The column is skipped and a report row is emitted.
 *
 * A proper autoNumber virtual column is planned for Phase 3 (P3.6).
 */
class AutoNumberConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'autoNumber';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			'autoNumber',
			'Column skipped. Nextcloud Tables rows have their own row ID. ' .
			'A dedicated autoNumber column type is planned for Phase 3.',
		);

		return null; // Column not created.
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		return null; // No column to write to.
	}
}
