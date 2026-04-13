<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: rollup (skip-and-report)
 *
 * Rollup fields are planned for Phase 4 (D4.2).
 */
class RollupSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'rollup';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		return 'Rollup fields are not yet supported (planned for Phase 4).';
	}
}
