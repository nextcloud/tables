<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: createdBy (lossy)
 *
 * Loss: the Tables row meta field `created_by` will hold the importing
 * user's Nextcloud ID, not the original Airtable creator.  The original
 * creator's display name is preserved in a text/line column so the
 * information is not lost entirely.
 */
class CreatedByConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'createdBy';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			'createdBy',
			'The row\'s created_by meta field will contain the importing user. ' .
			'The original Airtable creator\'s display name is stored in this text column.',
		);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn) . ' (Airtable)',
			type: 'text',
			subtype: 'line',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		// Airtable value: {id: "usrXXXX", email: "...", name: "..."} or a string.
		if (is_array($rawValue)) {
			return (string) ($rawValue['name'] ?? $rawValue['email'] ?? '');
		}
		return (string) $rawValue;
	}
}
