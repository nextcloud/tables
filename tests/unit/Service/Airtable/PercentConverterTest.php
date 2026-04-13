<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\PercentConverter;
use PHPUnit\Framework\TestCase;

class PercentConverterTest extends TestCase {

	private PercentConverter $converter;

	protected function setUp(): void {
		$this->converter = new PercentConverter();
	}

	public function testGetAirtableType(): void {
		$this->assertSame('percent', $this->converter->getAirtableType());
	}

	// -------------------------------------------------------------------------
	// toTablesColumn
	// -------------------------------------------------------------------------

	public function testToTablesColumnHasPercentSuffix(): void {
		$col = ['name' => 'Completion', 'typeOptions' => ['precision' => 1]];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('number', $dto->type);
		$this->assertSame('%', $dto->numberSuffix);
		$this->assertSame(1, $dto->numberDecimals);
		$this->assertEmpty($report);
	}

	public function testToTablesColumnZeroPrecisionYieldsNullDecimals(): void {
		$col = ['name' => 'Completion', 'typeOptions' => ['precision' => 0]];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNull($dto->numberDecimals);
	}

	// -------------------------------------------------------------------------
	// toTablesValue — Airtable stores fractions (0.5 = 50 %)
	// -------------------------------------------------------------------------

	public function toTablesValueProvider(): array {
		return [
			'null'           => [null,  null],
			'empty string'   => ['',    null],
			'half (0.5→50)'  => [0.5,   50.0],
			'full (1.0→100)' => [1.0,   100.0],
			'zero'           => [0.0,   0.0],
			'string fraction'=> ['0.25', 25.0],
		];
	}

	/** @dataProvider toTablesValueProvider */
	public function testToTablesValue(mixed $input, mixed $expected): void {
		$report = [];
		$result = $this->converter->toTablesValue($input, [], $report);
		$this->assertEquals($expected, $result);
		$this->assertEmpty($report);
	}
}
