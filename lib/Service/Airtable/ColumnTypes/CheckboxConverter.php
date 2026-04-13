<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/** Handles Airtable field type: checkbox */
class CheckboxConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'checkbox';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'selection',
			subtype: 'check',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null) {
			return null;
		}
		// SelectionCheckBusiness::PATTERN_POSITIVE includes PHP bool true.
		return (bool) $rawValue;
	}
}
