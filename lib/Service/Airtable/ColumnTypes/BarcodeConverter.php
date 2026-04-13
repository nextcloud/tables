<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Airtable\ColumnTypes;

use OCA\Tables\Dto\Column as ColumnDto;

/**
 * Handles Airtable field type: barcode (lossy)
 *
 * Loss: only the barcode string value is preserved; the barcode type
 * (e.g. upce, ean13) and any rendered image are discarded.
 * A proper text/barcode subtype is planned for Phase 3 (P3.5).
 */
class BarcodeConverter extends AbstractConverter {

	public function getAirtableType(): string {
		return 'barcode';
	}

	public function toTablesColumn(array $rawAirtableColumn, array &$reportRows): ?ColumnDto {
		$reportRows[] = $this->reportRow(
			$this->colName($rawAirtableColumn),
			'barcode',
			'Only the barcode string value is imported; the barcode type and rendered image are discarded. ' .
			'A barcode column subtype is planned for Phase 3.',
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
		// Airtable barcode value: {text: "12345", type: "upce"} or a plain string.
		if (is_array($rawValue)) {
			$text = (string) ($rawValue['text'] ?? '');
			return $text !== '' ? $text : null;
		}
		return (string) $rawValue;
	}
}
