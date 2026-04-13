<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\CurrencyConverter;
use PHPUnit\Framework\TestCase;

class CurrencyConverterTest extends TestCase {

	private CurrencyConverter $converter;

	protected function setUp(): void {
		$this->converter = new CurrencyConverter();
	}

	public function testGetAirtableType(): void {
		$this->assertSame('currency', $this->converter->getAirtableType());
	}

	// -------------------------------------------------------------------------
	// toTablesColumn
	// -------------------------------------------------------------------------

	public function testToTablesColumnWithSymbolAndDecimals(): void {
		$col = [
			'name' => 'Price',
			'typeOptions' => ['symbol' => '$', 'precision' => 2],
		];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('number', $dto->type);
		$this->assertSame(2, $dto->numberDecimals);
		$this->assertSame('$', $dto->numberPrefix);
		$this->assertEmpty($report);
	}

	public function testToTablesColumnWithoutSymbol(): void {
		$col = ['name' => 'Amount', 'typeOptions' => ['precision' => 0]];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNull($dto->numberPrefix);
	}

	public function testToTablesColumnDefaultPrecision(): void {
		$col = ['name' => 'Amount', 'typeOptions' => ['symbol' => '€']];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertSame(2, $dto->numberDecimals);
	}

	// -------------------------------------------------------------------------
	// toTablesValue
	// -------------------------------------------------------------------------

	public function toTablesValueProvider(): array {
		return [
			'null'         => [null, null],
			'empty string' => ['',   null],
			'float'        => [9.99, 9.99],
			'string float' => ['12.50', 12.50],
			'integer'      => [100,  100.0],
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
