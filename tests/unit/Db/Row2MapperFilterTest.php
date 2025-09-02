<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Column;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test class for Row2Mapper filtering functionality
 *
 * Tests various filter operations including text filters, number filters,
 * datetime filters, meta column filters, and edge cases.
 */
class Row2MapperFilterTest extends \OCA\Tables\Tests\Unit\Database\DatabaseTestCase {
	use Row2MapperTestDependencies;

	protected function setUp(): void {
		parent::setUp();
		$this->setupDependencies();
	}

	/**
	 * Converts filter column names (test identifiers) to actual column IDs
	 *
	 * This method handles both regular columns and meta columns (created_by, created_at, etc.)
	 * by mapping test identifiers to their corresponding database column IDs.
	 *
	 * @param array $filters Array of filters with column names as test identifiers
	 * @return array Array of filters with resolved column IDs
	 * @throws \InvalidArgumentException If column name is not found
	 */
	private function convertFilterColumnNamesToIds(array $filters): array {
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);

		$result = [];
		foreach ($filters as $filter) {
			$columnName = $filter['columnId'];
			$operator = $filter['operator'];
			$value = $filter['value'];

			if ($columnName === 'created_by') {
				$result[] = ['columnId' => Column::TYPE_META_CREATED_BY, 'operator' => $operator, 'value' => $value];
			} elseif ($columnName === 'created_at') {
				$result[] = ['columnId' => Column::TYPE_META_CREATED_AT, 'operator' => $operator, 'value' => $value];
			} elseif ($columnName === 'updated_by') {
				$result[] = ['columnId' => Column::TYPE_META_UPDATED_BY, 'operator' => $operator, 'value' => $value];
			} elseif ($columnName === 'updated_at') {
				$result[] = ['columnId' => Column::TYPE_META_UPDATED_AT, 'operator' => $operator, 'value' => $value];
			} elseif (isset($columnMapping[$columnName])) {
				$result[] = ['columnId' => $columnMapping[$columnName], 'operator' => $operator, 'value' => $value];
			} else {
				throw new \InvalidArgumentException("Unknown column name: $columnName");
			}
		}

		return $result;
	}

	/**
	 * Data provider for filter tests
	 *
	 * Provides test cases for various filter operations including:
	 * - Text filters (begins-with, ends-with, contains, etc.)
	 * - Number filters (greater-than, lower-than)
	 * - DateTime filters
	 * - Multiple filters (AND combinations)
	 * - Meta column filters
	 *
	 * @return array Array of test cases with filters, expected results, and descriptions
	 */
	public static function filterDataProvider(): array {
		return [
			// Text filters
			'begins-with matching' => [
				[['columnId' => 'name', 'operator' => 'begins-with', 'value' => 'Al']],
				['Alice'],
				'Filter names beginning with "Al"'
			],
			'begins-with no match' => [
				[['columnId' => 'name', 'operator' => 'begins-with', 'value' => 'Zz']],
				[],
				'Filter names beginning with "Zz" (no matches)'
			],
			'ends-with matching' => [
				[['columnId' => 'name', 'operator' => 'ends-with', 'value' => 'e']],
				['Alice', 'Charlie', 'Eve'],
				'Filter names ending with "e"'
			],
			'contains matching' => [
				[['columnId' => 'name', 'operator' => 'contains', 'value' => 'li']],
				['Alice', 'Charlie'],
				'Filter names containing "li"'
			],
			'does-not-contain matching' => [
				[['columnId' => 'name', 'operator' => 'does-not-contain', 'value' => 'li']],
				['Bob', 'Diana', 'Eve'],
				'Filter names not containing "li"'
			],
			'is-equal matching' => [
				[['columnId' => 'name', 'operator' => 'is-equal', 'value' => 'Bob']],
				['Bob'],
				'Filter names equal to "Bob"'
			],
			'is-not-equal matching' => [
				[['columnId' => 'name', 'operator' => 'is-not-equal', 'value' => 'Bob']],
				['Alice', 'Charlie', 'Diana', 'Eve'],
				'Filter names not equal to "Bob"'
			],
			'is-empty matching' => [
				[['columnId' => 'department', 'operator' => 'is-empty', 'value' => '']],
				[], // Assuming no empty departments in test data
				'Filter empty departments'
			],

			// Number filters
			'is-greater-than age' => [
				[['columnId' => 'age', 'operator' => 'is-greater-than', 'value' => '29']],
				['Bob', 'Eve'], // Ages 32, 30
				'Filter age greater than 29'
			],
			'is-lower-than age' => [
				[['columnId' => 'age', 'operator' => 'is-lower-than', 'value' => '27']],
				['Charlie', 'Diana'], // Ages 25, 25
				'Filter age lower than 27'
			],

			// DateTime filters
			'is-greater-than birthday' => [
				[['columnId' => 'birthday', 'operator' => 'is-greater-than', 'value' => '1995-01-01']],
				['Charlie', 'Diana', 'Alice'], // Born 1998
				'Filter birthday after 1995-01-01'
			],

			// Multiple filters (AND within group)
			'multiple filters AND' => [
				[
					['columnId' => 'department', 'operator' => 'is-equal', 'value' => 'IT'],
					['columnId' => 'age', 'operator' => 'is-greater-than', 'value' => '27']
				],
				['Alice', 'Eve'], // IT department AND age > 27
				'Filter IT department AND age > 27'
			],

			// Meta column filters
			'meta created_by filter' => [
				[['columnId' => 'created_by', 'operator' => 'is-equal', 'value' => 'user_alice']],
				['Alice'],
				'Filter by created_by meta column'
			],
		];
	}

	/**
	 * Test various filter operations using data provider
	 *
	 * @dataProvider filterDataProvider
	 * @param array $filter Filter configuration to apply
	 * @param array $expectedNameOrder Expected names in result order
	 * @param string $description Test case description
	 */
	public function testFindAllWithVariousFilters($filter, array $expectedNameOrder, string $description): void {
		$this->setupRealColumnMapper(self::$testTableId);

		$convertedFilter = [];
		if (isset($filter[0]) && is_array($filter[0]) && isset($filter[0][0])) {
			// Handle nested filter groups (OR between groups, AND within groups)
			foreach ($filter as $filterGroup) {
				$convertedFilter[] = $this->convertFilterColumnNamesToIds($filterGroup);
			}
		} else {
			// Handle single filter group
			$convertedFilter[] = $this->convertFilterColumnNamesToIds($filter);
		}

		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, $convertedFilter, null, 'test_user');

		$this->assertCount(count($expectedNameOrder), $rows, 'Should return ' . count($expectedNameOrder) . " rows for: $description");

		if (count($expectedNameOrder) > 0) {
			// Get the name column mapping to verify results
			$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);
			$nameColumnId = $columnMapping['name'];

			$actualNameOrder = array_map(
				fn ($row) => $this->getCellValue($row, $nameColumnId),
				$rows
			);

			$this->assertEqualsCanonicalizing(
				$expectedNameOrder,
				$actualNameOrder,
				"Failed filter test (ignoring order): $description"
			);
		}
	}

	/**
	 * Test special characters in filter values to ensure SQL injection protection
	 */
	public function testFilterWithSpecialCharacters(): void {
		$this->setupRealColumnMapper(self::$testTableId);

		// Test SQL injection protection
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);
		$nameColumnId = $columnMapping['name'];

		$filter = [[['columnId' => $nameColumnId, 'operator' => 'contains', 'value' => "'; DROP TABLE test; --"]]];

		// Should not throw exception and return no results
		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, $filter, null, 'test_user');
		$this->assertIsArray($rows, 'Filter with special characters should not cause SQL injection');
		$this->assertEmpty($rows, 'Filter with SQL injection attempt should return no results');
	}

	/**
	 * Test combined filter and sort functionality
	 */
	public function testCombinedFilterAndSort(): void {
		$this->setupRealColumnMapper(self::$testTableId);

		// Get column mappings for score column
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);
		$scoreColumnId = $columnMapping['score'];

		$filter = [[['columnId' => $scoreColumnId, 'operator' => 'is-greater-than', 'value' => '80']]]; // score > 80
		$sort = [['columnId' => $scoreColumnId, 'mode' => 'DESC']]; // sort by score descending

		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, $filter, $sort, 'test_user');

		$this->assertGreaterThan(0, count($rows), 'Combined filter and sort should return results');

		// Check that results are both filtered and sorted
		$scores = array_map(fn ($row) => (float)$this->getCellValue($row, $scoreColumnId), $rows);

		// All scores should be > 80
		foreach ($scores as $score) {
			$this->assertGreaterThan(80, $score, 'All results should match filter criteria');
		}

		// Scores should be in descending order
		$sortedScores = $scores;
		rsort($sortedScores);
		$this->assertEquals($sortedScores, $scores, 'Results should be sorted in descending order');
	}

	/**
	 * Test empty filter array
	 */
	public function testEmptyFilter(): void {
		$this->setupRealColumnMapper(self::$testTableId);

		$rows = $this->mapper->findAll(
			self::$testColumnIds,
			self::$testTableId,
			null,
			null,
			[], // Empty filter
			null,
			'test_user'
		);

		// Should return all test rows when no filter is applied
		$this->assertCount(5, $rows, 'Empty filter should return all rows');
	}
}
