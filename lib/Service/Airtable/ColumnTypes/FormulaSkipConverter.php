<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: formula (skip-and-report)
 *
 * Formula columns are planned for Phase 4 (D4.3).  The original Airtable
 * formula expression is preserved in the import report reason so the user
 * can recreate it manually.
 */
class FormulaSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'formula';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		$expression = (string) ($rawAirtableColumn['typeOptions']['formula']
			?? $rawAirtableColumn['typeOptions']['formulaTextParsed']
			?? '');

		$reason = 'Formula fields are not yet supported (planned for Phase 4).';
		if ($expression !== '') {
			$reason .= ' Original formula: ' . $expression;
		}
		return $reason;
	}
}
