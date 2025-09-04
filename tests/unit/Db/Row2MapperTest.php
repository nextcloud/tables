<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Column;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test class for Row2Mapper core functionality
 *
 * Tests sorting operations including single-column sorting, multi-column sorting,
 * meta column sorting, and edge cases with non-existent columns.
 */
class Row2MapperTest extends DatabaseTestCase {
	use Row2MapperTestDependencies;

	protected function setUp(): void {
		parent::setUp();
		$this->setupDependencies();
		$this->setupRealColumnMapper(self::$testTableId);
	}

	/**
	 * Data provider for sorting tests
	 *
	 * Provides comprehensive test cases for various sorting scenarios including:
	 * - Single column sorting (text, number, datetime)
	 * - Multi-column sorting combinations
	 * - Meta column sorting (created_by, etc.)
	 * - Both ascending and descending orders
	 *
	 * @return array Array of test cases with sort configuration, expected results, and descriptions
	 */
	public static function sortingDataProvider(): array {
		return [
			'Text column ASC' => [
				[['columnId' => 'name', 'mode' => 'ASC']],
				['Alice', 'Bob', 'Charlie', 'Diana', 'Eve'],
				'Sort by Name ascending'
			],
			'Text column DESC' => [
				[['columnId' => 'name', 'mode' => 'DESC']],
				['Eve', 'Diana', 'Charlie', 'Bob', 'Alice'],
				'Sort by Name descending'
			],
			'Number column (Age) ASC' => [
				[['columnId' => 'age', 'mode' => 'ASC']],
				['Charlie', 'Diana', 'Alice', 'Eve', 'Bob'],  // Ages: 25, 25, 28, 30, 32
				'Sort by Age ascending'
			],
			'Number column (Age) DESC' => [
				[['columnId' => 'age', 'mode' => 'DESC']],
				['Bob', 'Eve', 'Alice', 'Charlie', 'Diana'],  // Ages: 32, 30, 28, 25, 25
				'Sort by Age descending'
			],
			'Number column (Score) ASC' => [
				[['columnId' => 'score', 'mode' => 'ASC']],
				['Charlie', 'Alice', 'Diana', 'Bob', 'Eve'],  // Scores: 78.3, 85.5, 88.7, 92.0, 95.2
				'Sort by Score ascending'
			],
			'Number column (Score) DESC' => [
				[['columnId' => 'score', 'mode' => 'DESC']],
				['Eve', 'Bob', 'Diana', 'Alice', 'Charlie'],  // Scores: 95.2, 92.0, 88.7, 85.5, 78.3
				'Sort by Score descending'
			],
			'DateTime column ASC' => [
				[['columnId' => 'birthday', 'mode' => 'ASC']],
				['Bob', 'Eve', 'Alice', 'Charlie', 'Diana'],  // Birthdays: 1991, 1993, 1995, 1998, 1998
				'Sort by Birthday ascending'
			],
			'DateTime column DESC' => [
				[['columnId' => 'birthday', 'mode' => 'DESC']],
				['Diana', 'Charlie', 'Alice', 'Eve', 'Bob'],  // Birthdays: 1998, 1998, 1995, 1993, 1991
				'Sort by Birthday descending'
			],
			'Multi-column: Age ASC, Name ASC' => [
				[
					['columnId' => 'age', 'mode' => 'ASC'],
					['columnId' => 'name', 'mode' => 'ASC']
				],
				['Charlie', 'Diana', 'Alice', 'Eve', 'Bob'],  // Age 25: Charlie, Diana; Age 28: Alice; Age 30: Eve; Age 32: Bob
				'Sort by Age ascending, then Name ascending'
			],
			'Multi-column: Department ASC, Score DESC' => [
				[
					['columnId' => 'department', 'mode' => 'ASC'],
					['columnId' => 'score', 'mode' => 'DESC']
				],
				['Diana', 'Bob', 'Eve', 'Alice', 'Charlie'],  // Finance: Diana; HR: Bob; IT: Eve, Alice, Charlie (by score DESC)
				'Sort by Department ascending, then Score descending'
			],
			'Meta column: created_by ASC' => [
				[['columnId' => 'created_by', 'mode' => 'ASC']],
				['Alice', 'Bob', 'Charlie', 'Diana', 'Eve'],  // user_alice, user_bob, user_charlie, user_diana, user_eve
				'Sort by created_by meta column ascending'
			],
			'Meta column: created_by DESC' => [
				[['columnId' => 'created_by', 'mode' => 'DESC']],
				['Eve', 'Diana', 'Charlie', 'Bob', 'Alice'],  // user_eve, user_diana, user_charlie, user_bob, user_alice
				'Sort by created_by meta column descending'
			],
		];
	}

	/**
	 * Test various sorting operations using data provider
	 *
	 * @dataProvider sortingDataProvider
	 * @param array $sortWithNames Sort configuration with column names as test identifiers
	 * @param array $expectedNameOrder Expected names in result order
	 * @param string $description Test case description
	 */
	public function testFindAllWithVariousSorting(array $sortWithNames, array $expectedNameOrder, string $description): void {
		$this->setupRealColumnMapper(self::$testTableId);

		// Convert column names to IDs
		$sort = $this->convertColumnNamesToIds($sortWithNames);

		// Execute query with sorting
		// Check without limit/offset (full selection)
		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, null, $sort, 'test_user');
		$this->assertCount(5, $rows, "Should return all 5 rows for: $description");

		// Get name column ID using proper mapping
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);
		$nameColumnId = $columnMapping['name'];

		$actualNameOrder = array_map(fn ($row) => $this->getCellValue($row, $nameColumnId), $rows);
		$this->assertEqualsCanonicalizing($expectedNameOrder, $actualNameOrder, "Failed sorting test: $description");

		// Check with limit=3, offset=2 (should return 3 last in sorted order)
		$rowsLimited = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, 3, 2, null, $sort, 'test_user');
		$this->assertCount(3, $rowsLimited, "Should return 3 rows for limit=3, offset=2: $description");
		$actualNameOrderLimited = array_map(fn ($row) => $this->getCellValue($row, $nameColumnId), $rowsLimited);
		$expectedNameOrderLimited = array_slice($expectedNameOrder, 2, 3);
		$this->assertEqualsCanonicalizing($expectedNameOrderLimited, $actualNameOrderLimited, "Failed sorting test with limit/offset: $description");
	}

	/**
	 * Test for checking behavior with non-existent columnId
	 */
	public function testFindAllWithNonExistentColumnId(): void {
		$this->setupRealColumnMapper(self::$testTableId);

		// Use non-existent columnId (999999)
		$sortWithNonExistentColumn = [
			['columnId' => 999999, 'mode' => 'ASC']
		];

		// Execute query - expect it to execute without errors
		// DoesNotExistException is caught and sorting is skipped
		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, null, $sortWithNonExistentColumn, 'test_user');

		// Check that all rows were returned
		$this->assertCount(5, $rows, 'Should return all 5 rows even with non-existent columnId');

		// Check that the order remained unchanged (without sorting)
		// since sorting was skipped due to non-existent column
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);
		$nameColumnId = $columnMapping['name'];

		$actualNameOrder = array_map(fn ($row) => $this->getCellValue($row, $nameColumnId), $rows);

		// Expect order as in database (without sorting)
		$expectedDefaultOrder = ['Alice', 'Bob', 'Charlie', 'Diana', 'Eve'];
		$this->assertEquals($expectedDefaultOrder, $actualNameOrder, "Should return default order when columnId doesn't exist (sorting skipped)");
	}

	/**
	 * Test for checking behavior with mixed sorting array
	 * (existing + non-existent columns)
	 */
	public function testFindAllWithMixedExistingAndNonExistentColumns(): void {
		$this->setupRealColumnMapper(self::$testTableId);

		// Get column mappings for proper ID resolution
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);

		// Use mixed array: existing column + non-existent
		$mixedSort = [
			['columnId' => $columnMapping['age'], 'mode' => 'ASC'],  // Age (existing)
			['columnId' => 999999, 'mode' => 'DESC'],                   // Non-existent
			['columnId' => $columnMapping['name'], 'mode' => 'ASC']    // Name (existing)
		];

		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, null, $mixedSort, 'test_user');

		// Check that all rows were returned
		$this->assertCount(5, $rows, 'Should return all 5 rows with mixed sort array');

		// Check that sorting works only for existing columns
		// Expect sorting by Age ASC, then by Name ASC (non-existent column is ignored)
		$nameColumnId = $columnMapping['name'];
		$ageColumnId = $columnMapping['age'];

		$actualNameOrder = array_map(fn ($row) => $this->getCellValue($row, $nameColumnId), $rows);
		$actualAgeOrder = array_map(fn ($row) => $this->getCellValue($row, $ageColumnId), $rows);

		// Expect: Age 25 (Charlie, Diana), Age 28 (Alice), Age 30 (Eve), Age 32 (Bob)
		// Within same age - sorting by Name ASC
		$expectedNameOrder = ['Charlie', 'Diana', 'Alice', 'Eve', 'Bob'];
		$expectedAgeOrder = [25, 25, 28, 30, 32];

		$this->assertEquals($expectedNameOrder, $actualNameOrder, 'Should sort by existing columns only');
		$this->assertEquals($expectedAgeOrder, $actualAgeOrder, 'Should sort by Age ASC, then Name ASC');
	}

	/**
	 * Converts column names to IDs for Row2Mapper
	 */
	private function convertColumnNamesToIds(array $sortWithNames): array {
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);

		$result = [];
		foreach ($sortWithNames as $sortItem) {
			$columnName = $sortItem['columnId'];
			$mode = $sortItem['mode'];

			if ($columnName === 'created_by') {
				$result[] = ['columnId' => Column::TYPE_META_CREATED_BY, 'mode' => $mode];
			} elseif (isset($columnMapping[$columnName])) {
				$result[] = ['columnId' => $columnMapping[$columnName], 'mode' => $mode];
			} else {
				throw new \InvalidArgumentException("Unknown column name: $columnName");
			}
		}

		return $result;
	}
}
