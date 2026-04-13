<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: lookup (skip-and-report)
 *
 * Lookup fields depend on both Phase 2 (reference columns) and Phase 4
 * (lookup column type, D4.1) before they can be imported.
 */
class LookupSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'lookup';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		return 'Lookup fields require the reference column type (Phase 2) and the '
			. 'lookup column type (Phase 4) to be implemented before they can be imported.';
	}
}
