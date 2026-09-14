<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class RowCellNumberMapperTest extends TestCase {

	private RowCellNumberMapper $mapper;
	private Column $column;

	public function setUp(): void {
		$this->mapper = new RowCellNumberMapper($this->createMock(IDBConnection::class));
		// getNumberDecimals() is resolved through Entity's __call magic and cannot be mocked
		$this->column = new Column();
		$this->column->setNumberDecimals(0);
	}

	public function applyDataProvider(): array {
		return [
			'integer value' => [42, 42.0],
			'numeric string' => ['42', 42.0],
			'float value' => [3.5, 3.5],
			'explicit zero' => [0, 0.0],
			// A cleared field must persist as NULL. Writing 0.0 here would invent a
			// value the user never entered, and would satisfy a mandatory constraint.
			'empty string' => ['', null],
			'null value' => [null, null],
			'non numeric' => ['abc', null],
		];
	}

	/**
	 * @dataProvider applyDataProvider
	 */
	public function testApplyDataToEntity(mixed $data, ?float $expected): void {
		$cell = new RowCellNumber();
		$this->mapper->applyDataToEntity($this->column, $cell, $data);

		$this->assertSame($expected, $cell->getValue());
	}

	public function formatRowDataProvider(): array {
		return [
			'integer value' => [42.0, 42],
			'explicit zero' => [0.0, 0],
			// A NULL cell means "no value" and must not read back as 0.
			'null value' => [null, null],
			'empty string' => ['', null],
		];
	}

	/**
	 * @dataProvider formatRowDataProvider
	 */
	public function testFormatRowData(mixed $stored, ?int $expected): void {
		$this->assertSame($expected, $this->mapper->formatRowData($this->column, ['value' => $stored]));
	}
}
