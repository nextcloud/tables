<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;

class RelationLookupBusiness extends SuperBusiness {
	/**
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		// Relation lookup is a virtual column, values come from the related table
		// No parsing needed for input as this column is read-only
		return '';
	}

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		// Virtual column, cannot be set directly
		return false;
	}

	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		// Virtual column, validation not applicable
		// Values are derived from the relation column
	}

	/**
	 * @param mixed $value
	 * @param Column $column
	 * @return bool
	 */
	public function canBeParsedDisplayValue($value, Column $column): bool {
		// Virtual column, display values come from relation data
		return false;
	}

	/**
	 * @param mixed $value
	 * @param Column $column
	 * @return string
	 */
	public function parseDisplayValue($value, Column $column): string {
		// Virtual column, display values come from relation data
		return '';
	}
}
