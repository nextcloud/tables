<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: button (skip-and-report)
 *
 * Button fields trigger server-side actions in Airtable and have no
 * equivalent in Nextcloud Tables.
 */
class ButtonSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'button';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		return 'Button fields trigger Airtable server-side actions and have no equivalent in Nextcloud Tables.';
	}
}
