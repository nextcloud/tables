<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: multipleCollaborators (lossy)
 *
 * Loss: same as singleCollaborator — Airtable users cannot be mapped to
 * Nextcloud accounts.  All collaborator display names are joined with ", "
 * and stored as a single text/line value.
 */
class MultipleCollaboratorsConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'multipleCollaborators';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			'multipleCollaborators',
			'Imported as plain text (comma-separated collaborator display names). ' .
			'Airtable user identities cannot be automatically mapped to Nextcloud accounts.',
		);

		return new ColumnDto(
			title: $this->colName($rawAirtableColumn),
			type: 'text',
			subtype: 'line',
		);
	}

	public function toTablesValue(mixed $rawValue, array $rawAirtableColumn, array &$reportRows): mixed {
		if ($rawValue === null || $rawValue === [] || $rawValue === '') {
			return null;
		}
		// Airtable value: [{id: "usrXXXX", email: "...", name: "..."}, …]
		if (is_array($rawValue)) {
			$names = array_map(
				static fn (mixed $c): string => is_array($c)
					? (string) ($c['name'] ?? $c['email'] ?? '')
					: (string) $c,
				$rawValue
			);
			$names = array_filter($names, static fn (string $n): bool => $n !== '');
			return $names !== [] ? implode(', ', $names) : null;
		}
		return (string) $rawValue;
	}
}
