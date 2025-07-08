<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SelectionBusinessTest extends TestCase {

	private SelectionBusiness $selectionBusiness;
	private LoggerInterface $logger;
	private Column $column;

	public function setUp(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->selectionBusiness = new SelectionBusiness($this->logger);

		$this->column = $this->createMock(Column::class);
		$this->column->method('getSelectionOptionsArray')
			->willReturn([
				['id' => 1, 'label' => 'Option 1'],
				['id' => 2, 'label' => 'Option 2'],
				['id' => 3, 'label' => 'Option 3'],
				['id' => 4, 'label' => '1'],
			]);
	}

	public function parseValueProvider(): array {
		return [
			'valid integer value' => [2, '2'],
			'valid string value' => ['2', '2'],
			'valid string value for numeric option' => ['4', '4'],
			'invalid value' => [5, ''],
			'null value' => [null, ''],
			'empty string' => ['', ''],
			'float value' => [1.5, ''],
			'boolean value' => [true, ''],
			'array value' => [[1], ''],
		];
	}

	/**
	 * @dataProvider parseValueProvider
	 */
	public function testParseValue($value, string $expected): void {
		$result = $this->selectionBusiness->parseValue($value, $this->column);
		$this->assertEquals($expected, $result);
	}

	public function parseDisplayValueProvider(): array {
		return [
			'valid label' => ['Option 2', '2'],
			'invalid label' => ['Invalid Option', ''],
			'valid label for numeric option' => ['1', '4'],
			'null value' => [null, ''],
			'empty string' => ['', ''],
			'boolean value' => [true, ''],
			'array value' => [[1], ''],
		];
	}

	/**
	 * @dataProvider parseDisplayValueProvider
	 */
	public function testParseDisplayValue($value, string $expected): void {
		$result = $this->selectionBusiness->parseDisplayValue($value, $this->column);
		$this->assertEquals($expected, $result);
	}

	public function canBeParsedProvider(): array {
		return [
			'valid integer 1' => [1, true],
			'valid string 4' => ['4', true],
			'invalid integer' => [5, false],
			'invalid integer 0' => [0, false],
			'null value' => [null, true],
			'empty string' => ['', false],
			'float value' => [1.5, false],
			'boolean value' => [true, false],
			'array value' => [[1], false],
		];
	}

	/**
	 * @dataProvider canBeParsedProvider
	 */
	public function testCanBeParsed($value, bool $expected): void {
		$result = $this->selectionBusiness->canBeParsed($value, $this->column);
		$this->assertEquals($expected, $result);
	}

	public function canBeParsedDisplayValueProvider(): array {
		return [
			'valid label' => ['Option 2', true],
			'invalid label' => ['Invalid Option', false],
			'valid label for numeric option' => ['1', true],
			'null value' => [null, true],
			'empty string' => ['', false],
			'boolean value' => [true, false],
			'array value' => [[1], false],
		];
	}

	/**
	 * @dataProvider canBeParsedDisplayValueProvider
	 */
	public function testCanBeParsedDisplayValue($value, bool $expected): void {
		$result = $this->selectionBusiness->canBeParsedDisplayValue($value, $this->column);
		$this->assertEquals($expected, $result);
	}

	public function withoutColumnProvider(): array {
		return [
			'parseValue' => ['parseValue', 1, ''],
			'parseDisplayValue' => ['parseDisplayValue', 'Option 1', ''],
			'canBeParsed' => ['canBeParsed', 1, false],
			'canBeParsedDisplayValue' => ['canBeParsedDisplayValue', 'Option 1', false],
		];
	}

	/**
	 * @dataProvider withoutColumnProvider
	 */
	public function testMethodsWithoutColumn(string $method, $value, $expected): void {
		$result = $this->selectionBusiness->$method($value, null);
		$this->assertEquals($expected, $result);
	}

}
