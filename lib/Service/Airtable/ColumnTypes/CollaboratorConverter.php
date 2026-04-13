<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: singleCollaborator (lossy)
 *
 * Loss: Airtable users cannot be reliably mapped to Nextcloud user IDs at
 * import time (no shared directory).  The collaborator's display name is
 * stored as plain text instead.  A proper usergroup column can be created
 * manually once users are provisioned in Nextcloud.
 */
class CollaboratorConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'singleCollaborator';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			'singleCollaborator',
			'Imported as plain text (collaborator display name). ' .
			'Airtable user identities cannot be automatically mapped to Nextcloud accounts.',
		);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'text',
			subtype: 'line',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === '') {
			return null;
		}
		// Airtable collaborator value: {id: "usrXXXX", email: "...", name: "..."}
		if (is_array($rawValue)) {
			return (string) ($rawValue['name'] ?? $rawValue['email'] ?? '');
		}
		return (string) $rawValue;
	}
}
