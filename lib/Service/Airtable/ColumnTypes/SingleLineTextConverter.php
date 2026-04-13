<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

/** Handles Airtable field type: singleLineText (alias of text). */
class SingleLineTextConverter extends TextConverter {

	public function getAirtableType(): string {
		return 'singleLineText';
	}
}
