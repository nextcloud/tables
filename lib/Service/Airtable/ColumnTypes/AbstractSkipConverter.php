<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Base class for all skip-and-report converters.
 *
 * A skip-and-report converter creates no Tables column and no cell value;
 * it only appends an entry to the import report so the user knows which
 * Airtable fields were omitted and why.
 *
 * Subclasses must implement:
 *   - getAirtableType(): string
 *   - getSkipReason(array $rawAirtableColumn): string
 */
abstract class AbstractSkipConverter extends AbstractConverter {

	/**
	 * Human-readable explanation of why this field type is skipped, shown
	 * verbatim in the "Reason" column of the import report table.
	 *
	 * Implementations may inspect $rawAirtableColumn to include field-specific
	 * details (e.g. the formula expression for a formula field).
	 */
	abstract protected function getSkipReason(array $rawAirtableColumn): string;

	final public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			$this->getAirtableType(),
			$this->getSkipReason($rawAirtableColumn),
		);

		return null; // No column created.
	}

	final public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		return null; // No column to write to.
	}
}
