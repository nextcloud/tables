<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Contract that every Airtable-field-type converter must fulfil.
 *
 * One implementation per Airtable field type lives in
 * lib/Service/Airtable/ColumnTypes/.  Converters are registered with
 * AirtableColumnTypeRegistry in Application::boot().
 *
 * A converter is responsible for two concerns:
 *
 *  1. Schema conversion  – map the Airtable column descriptor to a
 *     Nextcloud Tables ColumnDto (or return null to skip the column).
 *
 *  2. Value conversion   – normalise a single Airtable cell value to the
 *     format expected by RowService / the Tables row-cell storage layer.
 *
 * Both methods append import-report rows to $reportRows whenever
 * information is lost (lossy downgrade) or a column/value is skipped
 * entirely.  Each report-row element is an associative array with at
 * minimum the keys expected by AirtableImportReportBuilder:
 *
 *   [
 *     'object_name' => string,   // Airtable field name
 *     'object_type' => string,   // 'field' or 'value'
 *     'airtable_type' => string, // raw Airtable type string
 *     'reason'       => string,  // human-readable explanation
 *   ]
 */
interface AirtableColumnTypeInterface {

	/**
	 * The Airtable field-type string this converter handles,
	 * e.g. 'text', 'number', 'multipleAttachments'.
	 *
	 * Must match the value of the `type` key in the raw Airtable column
	 * descriptor returned by the schema endpoint.
	 */
	public function getAirtableType(): string;

	/**
	 * Map an Airtable column descriptor to a Nextcloud Tables ColumnDto.
	 *
	 * Return null to skip this column entirely (e.g. formula, lookup).
	 * Append a report row to $reportRows for every skip or lossy downgrade.
	 *
	 * @param array<string, mixed> $rawAirtableColumn  Raw column object from
	 *        the Airtable schema endpoint (keys: id, name, type, typeOptions…).
	 * @param array<int, array<string, string>> $reportRows  Accumulator for
	 *        import-report rows; passed by reference.
	 */
	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto;

	/**
	 * Convert a single Airtable cell value to the Tables storage format.
	 *
	 * Return null for empty or unsupported values.
	 * Append a report row to $reportRows on per-value issues.
	 *
	 * @param mixed                $rawValue           Raw value from Airtable
	 *        row data.
	 * @param array<string, mixed> $rawAirtableColumn  The column descriptor for
	 *        this field (same shape as in toTablesColumn).
	 * @param array<int, array<string, string>> $reportRows  Accumulator for
	 *        import-report rows; passed by reference.
	 */
	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed;
}
