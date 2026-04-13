<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\CheckboxConverter;
use PHPUnit\Framework\TestCase;

class CheckboxConverterTest extends TestCase {

	private CheckboxConverter $converter;

	protected function setUp(): void {
		$this->converter = new CheckboxConverter();
	}

	public function testGetAirtableType(): void {
		$this->assertSame('checkbox', $this->converter->getAirtableType());
	}

	// -------------------------------------------------------------------------
	// toTablesColumn
	// -------------------------------------------------------------------------

	public function testToTablesColumnReturnsCheckSubtype(): void {
		$col = ['name' => 'Done'];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('selection', $dto->type);
		$this->assertSame('check', $dto->subtype);
		$this->assertEmpty($report);
	}

	// -------------------------------------------------------------------------
	// toTablesValue
	// -------------------------------------------------------------------------

	public function toTablesValueProvider(): array {
		return [
			'null returns null'     => [null,  null],
			'true stays true'       => [true,  true],
			'false stays false'     => [false, false],
			'1 coerces to true'     => [1,     true],
			'0 coerces to false'    => [0,     false],
			'"true" coerces true'   => ['true', true],
			'empty string is false' => ['',    null],   // null because rawValue===''? No — '' is falsy in PHP but !== null
		];
	}

	public function testToTablesValueNull(): void {
		$report = [];
		$this->assertNull($this->converter->toTablesValue(null, [], $report));
	}

	public function testToTablesValueTrue(): void {
		$report = [];
		$this->assertTrue($this->converter->toTablesValue(true, [], $report));
	}

	public function testToTablesValueFalse(): void {
		$report = [];
		$this->assertFalse($this->converter->toTablesValue(false, [], $report));
	}

	public function testToTablesValueOneIsTrue(): void {
		$report = [];
		$this->assertTrue($this->converter->toTablesValue(1, [], $report));
	}

	public function testToTablesValueZeroIsFalse(): void {
		$report = [];
		$this->assertFalse($this->converter->toTablesValue(0, [], $report));
	}

	public function testToTablesValueNoReportEntries(): void {
		$report = [];
		$this->converter->toTablesValue(true, ['name' => 'Done'], $report);
		$this->assertEmpty($report);
	}
}
