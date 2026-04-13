<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\DurationConverter;
use PHPUnit\Framework\TestCase;

class DurationConverterTest extends TestCase {

	private DurationConverter $converter;

	protected function setUp(): void {
		$this->converter = new DurationConverter();
	}

	public function testGetAirtableType(): void {
		$this->assertSame('duration', $this->converter->getAirtableType());
	}

	// -------------------------------------------------------------------------
	// toTablesColumn — lossy: emits report row, returns number column
	// -------------------------------------------------------------------------

	public function testToTablesColumnEmitsReportRow(): void {
		$col = ['name' => 'Duration'];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('number', $dto->type);
		$this->assertSame('s', $dto->numberSuffix);
		$this->assertCount(1, $report);
		$this->assertSame('Duration', $report[0]['object_name']);
		$this->assertSame('duration', $report[0]['airtable_type']);
		$this->assertSame('field', $report[0]['object_type']);
	}

	// -------------------------------------------------------------------------
	// toTablesValue
	// -------------------------------------------------------------------------

	public function toTablesValueProvider(): array {
		return [
			'null'         => [null,  null],
			'empty string' => ['',    null],
			'seconds int'  => [3600,  3600],
			'string int'   => ['120', 120],
			'float cast'   => [90.5,  90],
		];
	}

	/** @dataProvider toTablesValueProvider */
	public function testToTablesValue(mixed $input, mixed $expected): void {
		$report = [];
		$result = $this->converter->toTablesValue($input, [], $report);
		$this->assertEquals($expected, $result);
	}

	public function testToTablesValueNoAdditionalReportEntries(): void {
		$report = [];
		$this->converter->toTablesValue(60, [], $report);
		$this->assertEmpty($report);
	}
}
