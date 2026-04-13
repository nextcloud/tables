<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\CreatedTimeConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\DateConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\DateTimeConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\LastModifiedTimeConverter;
use PHPUnit\Framework\TestCase;

class DateConverterTest extends TestCase {

	// -------------------------------------------------------------------------
	// DateConverter
	// -------------------------------------------------------------------------

	public function testDateGetAirtableType(): void {
		$this->assertSame('date', (new DateConverter())->getAirtableType());
	}

	public function testDateToTablesColumnReturnsDateSubtype(): void {
		$col = ['name' => 'Due date'];
		$report = [];
		$dto = (new DateConverter())->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('datetime', $dto->type);
		$this->assertSame('date', $dto->subtype);
		$this->assertEmpty($report);
	}

	public function testDateToTablesValuePassthroughIso(): void {
		$report = [];
		$result = (new DateConverter())->toTablesValue('2023-01-15', [], $report);
		$this->assertSame('2023-01-15', $result);
	}

	public function testDateToTablesValueNullReturnsNull(): void {
		$report = [];
		$this->assertNull((new DateConverter())->toTablesValue(null, [], $report));
	}

	public function testDateToTablesValueEmptyReturnsNull(): void {
		$report = [];
		$this->assertNull((new DateConverter())->toTablesValue('', [], $report));
	}

	// -------------------------------------------------------------------------
	// DateTimeConverter
	// -------------------------------------------------------------------------

	public function testDateTimeGetAirtableType(): void {
		$this->assertSame('dateTime', (new DateTimeConverter())->getAirtableType());
	}

	public function testDateTimeToTablesColumnReturnsDatetimeSubtype(): void {
		$col = ['name' => 'Timestamp'];
		$report = [];
		$dto = (new DateTimeConverter())->toTablesColumn($col, $report);

		$this->assertSame('datetime', $dto->type);
		$this->assertSame('datetime', $dto->subtype);
		$this->assertEmpty($report);
	}

	public function testDateTimeToTablesValuePassthroughIso(): void {
		$report = [];
		$result = (new DateTimeConverter())->toTablesValue('2023-01-15T10:30:00.000Z', [], $report);
		$this->assertSame('2023-01-15T10:30:00.000Z', $result);
	}

	// -------------------------------------------------------------------------
	// CreatedTimeConverter
	// -------------------------------------------------------------------------

	public function testCreatedTimeGetAirtableType(): void {
		$this->assertSame('createdTime', (new CreatedTimeConverter())->getAirtableType());
	}

	public function testCreatedTimeToTablesColumnReturnsDatetimeSubtype(): void {
		$col = ['name' => 'Created'];
		$report = [];
		$dto = (new CreatedTimeConverter())->toTablesColumn($col, $report);

		$this->assertSame('datetime', $dto->type);
		$this->assertSame('datetime', $dto->subtype);
		$this->assertEmpty($report);
	}

	public function testCreatedTimeToTablesValuePassthrough(): void {
		$report = [];
		$result = (new CreatedTimeConverter())->toTablesValue('2024-06-01T00:00:00.000Z', [], $report);
		$this->assertSame('2024-06-01T00:00:00.000Z', $result);
	}

	// -------------------------------------------------------------------------
	// LastModifiedTimeConverter
	// -------------------------------------------------------------------------

	public function testLastModifiedTimeGetAirtableType(): void {
		$this->assertSame('lastModifiedTime', (new LastModifiedTimeConverter())->getAirtableType());
	}
}
