<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\NumberConverter;
use PHPUnit\Framework\TestCase;

class NumberConverterTest extends TestCase {

	private NumberConverter $converter;

	protected function setUp(): void {
		$this->converter = new NumberConverter();
	}

	public function testGetAirtableType(): void {
		$this->assertSame('number', $this->converter->getAirtableType());
	}

	// -------------------------------------------------------------------------
	// toTablesColumn
	// -------------------------------------------------------------------------

	public function testToTablesColumnIntegerType(): void {
		$col = ['name' => 'Count', 'type' => 'number'];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('number', $dto->type);
		$this->assertNull($dto->numberDecimals);
		$this->assertEmpty($report);
	}

	public function testToTablesColumnWithDecimals(): void {
		$col = ['name' => 'Price', 'type' => 'number', 'typeOptions' => ['precision' => 2]];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame(2, $dto->numberDecimals);
		$this->assertEmpty($report);
	}

	public function testToTablesColumnZeroPrecisionYieldsNullDecimals(): void {
		$col = ['name' => 'Count', 'typeOptions' => ['precision' => 0]];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNull($dto->numberDecimals);
	}

	// -------------------------------------------------------------------------
	// toTablesValue
	// -------------------------------------------------------------------------

	public function toTablesValueProvider(): array {
		return [
			'null returns null'         => [null,   null],
			'empty string returns null' => ['',     null],
			'integer preserved'         => [42,     42],
			'float preserved'           => [3.14,   3.14],
			'string float cast'         => ['1.5',  1.5],
			'string int cast to float'  => ['7',    7.0],
		];
	}

	/** @dataProvider toTablesValueProvider */
	public function testToTablesValue(mixed $input, mixed $expected): void {
		$report = [];
		$result = $this->converter->toTablesValue($input, [], $report);
		$this->assertEquals($expected, $result);
		$this->assertEmpty($report);
	}

	public function testToTablesValueIntIsPreservedAsInt(): void {
		$report = [];
		$result = $this->converter->toTablesValue(5, [], $report);
		$this->assertIsInt($result);
	}
}
