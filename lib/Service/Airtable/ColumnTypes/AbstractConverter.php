<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Service\Airtable\AirtableColumnTypeInterface;

/**
 * Shared helpers for all Airtable column-type converters.
 */
abstract class AbstractConverter implements AirtableColumnTypeInterface {

	/** Extract the human-readable column name from a raw Airtable column descriptor. */
	protected function colName(array $rawAirtableColumn): string {
		return (string) ($rawAirtableColumn['name'] ?? '(unknown)');
	}

	/**
	 * Build a single import-report row array in the shape expected by
	 * AirtableImportReportBuilder.
	 *
	 * @param string $objectType  'field' (column skipped/downgraded) or
	 *                            'value' (individual cell value could not be
	 *                            converted faithfully).
	 * @return array<string, string>
	 */
	protected function reportRow(
		string $name,
		string $airtableType,
		string $reason,
		string $objectType = 'field',
	): array {
		return [
			'object_name'   => $name,
			'object_type'   => $objectType,
			'airtable_type' => $airtableType,
			'reason'        => $reason,
		];
	}
}
