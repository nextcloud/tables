<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\RatingConverter;
use PHPUnit\Framework\TestCase;

class RatingConverterTest extends TestCase {

	private RatingConverter $converter;

	protected function setUp(): void {
		$this->converter = new RatingConverter();
	}

	public function testGetAirtableType(): void {
		$this->assertSame('rating', $this->converter->getAirtableType());
	}

	// -------------------------------------------------------------------------
	// toTablesColumn
	// -------------------------------------------------------------------------

	public function testToTablesColumnWithMax(): void {
		$col = ['name' => 'Stars', 'typeOptions' => ['max' => 10]];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('number', $dto->type);
		$this->assertSame('stars', $dto->subtype);
		$this->assertSame(0.0, $dto->numberMin);
		$this->assertSame(10.0, $dto->numberMax);
		$this->assertEmpty($report);
	}

	public function testToTablesColumnDefaultMaxIs5(): void {
		$col = ['name' => 'Stars'];
		$report = [];

		$dto = $this->converter->toTablesColumn($col, $report);

		$this->assertSame(5.0, $dto->numberMax);
	}

	// -------------------------------------------------------------------------
	// toTablesValue
	// -------------------------------------------------------------------------

	public function toTablesValueProvider(): array {
		return [
			'null'         => [null, null],
			'empty string' => ['',   null],
			'int 3'        => [3,    3],
			'string "5"'   => ['5',  5],
			'float cast'   => [4.9,  4],
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
