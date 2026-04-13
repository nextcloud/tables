<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/**
 * Handles Airtable field type: aiText (skip-and-report)
 *
 * AI-generated text fields rely on Airtable's AI integration and have
 * no equivalent in Nextcloud Tables at this time.
 */
class AiTextSkipConverter extends AbstractSkipConverter {

	public function getAirtableType(): string {
		return 'aiText';
	}

	protected function getSkipReason(array $rawAirtableColumn): string {
		return 'AI-generated text fields rely on Airtable\'s AI integration '
			. 'and are not supported in Nextcloud Tables.';
	}
}
