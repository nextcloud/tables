<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: externalSyncSource (skip-and-report)
 *
 * External sync source fields are managed by Airtable's own sync
 * infrastructure and cannot be reproduced in Nextcloud Tables.
 */
class ExternalSyncSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'externalSyncSource';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		return 'External sync source fields are managed by Airtable\'s sync infrastructure '
			. 'and have no equivalent in Nextcloud Tables.';
	}
}
