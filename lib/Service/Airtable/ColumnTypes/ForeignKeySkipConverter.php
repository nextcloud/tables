<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: foreignKey / linked record (skip-and-report)
 *
 * Linked record fields will be fully imported in Phase 2 once the
 * reference column type and LinkRowConverter are implemented (B2.9).
 */
class ForeignKeySkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'foreignKey';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		$linkedTable = (string) ($rawAirtableColumn['typeOptions']['foreignTableName']
			?? $rawAirtableColumn['typeOptions']['linkedTableId']
			?? 'unknown table');

		return "Linked record fields (linked to \"$linkedTable\") will be supported in Phase 2. "
			. 'Re-import with Phase 2 enabled to include linked records.';
	}
}
