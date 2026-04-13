<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: count (skip-and-report)
 *
 * Count fields are planned for Phase 4 (D4.2).
 */
class CountSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'count';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		return 'Count fields are not yet supported (planned for Phase 4).';
	}
}
