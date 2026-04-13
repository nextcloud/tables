<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: phoneNumber (lossy)
 *
 * Loss: mapped to text/line — no tel: affordance.
 * Resolved in Phase 3 when the text/phone subtype is implemented (P3.2).
 */
class PhoneConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'phoneNumber';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			'phoneNumber',
			'Imported as plain text. The tel: link affordance is not yet supported (Phase 3).',
		);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'text',
			subtype: 'line',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		return (string) $rawValue;
	}
}
