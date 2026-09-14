<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NumberBusinessTest extends TestCase {

	private NumberBusiness $numberBusiness;
	private Column $column;

	public function setUp(): void {
		$this->numberBusiness = new NumberBusiness($this->createMock(LoggerInterface::class));
		$this->column = new Column();
	}

	public function parseValueProvider(): array {
		return [
			'integer value' => [42, '42'],
			'numeric string' => ['42', '42'],
			'float value' => [3.5, '3.5'],
			'explicit zero' => [0, '0'],
			'explicit zero as string' => ['0', '0'],
			'null value' => [null, ''],
			// An emptied field is sent as '' by the row edit modal. It must stay
			// empty instead of being parsed into a 0 that was never entered.
			'empty string' => ['', ''],
		];
	}

	/**
	 * @dataProvider parseValueProvider
	 */
	public function testParseValue(mixed $value, string $expected): void {
		$this->assertSame($expected, $this->numberBusiness->parseValue($value, $this->column));
	}

	public function testEmptyValueDoesNotBecomeZero(): void {
		$zero = json_decode($this->numberBusiness->parseValue(0, $this->column), true);
		$emptied = json_decode($this->numberBusiness->parseValue('', $this->column), true);

		$this->assertSame(0, $zero);
		$this->assertNull($emptied);
	}
}
