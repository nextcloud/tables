<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
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

	public function testBulkFetchChunksRowsAndColumnsUsingOneQueryBuilder(): void {
		$qb = $this->createMock(IQueryBuilder::class);
		foreach (['select', 'from', 'where', 'andWhere'] as $method) {
			$qb->method($method)->willReturnSelf();
		}
		$qb->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$parameters = [];
		$qb->method('setParameter')->willReturnCallback(function (string $name, array $values, int $type) use (&$parameters, $qb) {
			$this->assertSame(IQueryBuilder::PARAM_INT_ARRAY, $type);
			$this->assertLessThanOrEqual(1_000, count($values));
			$parameters[$name] = $values;
			return $qb;
		});
		$chunks = [];
		$qb->expects($this->exactly(4))->method('executeQuery')
			->willReturnCallback(function () use (&$chunks, &$parameters) {
				$chunks[] = [count($parameters['rowIds']), count($parameters['columnIds'])];
				$result = $this->createMock(IResult::class);
				foreach (['fetch', 'fetchAssociative'] as $method) {
					$result->method($method)->willReturnOnConsecutiveCalls(
						['id' => count($chunks), 'row_id' => $parameters['rowIds'][0], 'column_id' => $parameters['columnIds'][0], 'value' => 42],
						false,
					);
				}
				return $result;
			});
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('getQueryBuilder')->willReturn($qb);
		$mapper = new RowCellNumberMapper($db);

		$cells = $mapper->findAllByRowIdsAndColumnIds(range(1, 1_001), range(1, 1_001));

		$this->assertSame([[1_000, 1_000], [1, 1_000], [1_000, 1], [1, 1]], $chunks);
		$this->assertSame([1, 2, 3, 4], array_map(fn (RowCellNumber $cell) => $cell->getId(), $cells));
	}

	public function testBulkFetchSkipsEmptyInputs(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('getQueryBuilder');
		$mapper = new RowCellNumberMapper($db);

		$this->assertSame([], $mapper->findAllByRowIdsAndColumnIds([], [1]));
		$this->assertSame([], $mapper->findAllByRowIdsAndColumnIds([1], []));
	}
}
