<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class Row2MapperTest extends DatabaseTestCase {
	private Row2Mapper $mapper;
	private ColumnMapper|MockObject $columnMapper;
	private RowSleeveMapper $rowSleeveMapper;
	private UserHelper|MockObject $userHelper;
	private ColumnsHelper|MockObject $columnsHelper;
	private LoggerInterface|MockObject $logger;
	private CircleHelper|MockObject $circleHelper;

	private static bool $testDataInitialized = false;
	private static int $testTableId;
	private static array $testColumnIds = [];
	private static array $testRowIds = [];
	private static array $testDataResult = [];

	protected function setUp(): void {
		parent::setUp();

		$this->columnMapper = $this->createMock(ColumnMapper::class);
		$this->userHelper = $this->createMock(UserHelper::class);
		$this->circleHelper = $this->createMock(CircleHelper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->rowSleeveMapper = new RowSleeveMapper($this->connectionAdapter, $this->logger);
		$this->columnsHelper = new ColumnsHelper($this->userHelper, $this->circleHelper);

		$this->mapper = new Row2Mapper(
			'test_user',
			$this->connectionAdapter,
			$this->logger,
			$this->userHelper,
			$this->rowSleeveMapper,
			$this->columnsHelper,
			$this->columnMapper
		);

		if (!self::$testDataInitialized) {
			$this->initializeTestData();
			self::$testDataInitialized = true;
		}
	}

	private function initializeTestData(): void {
		$result = $this->createCompleteTestTable(
			['test_ident' => 'sort_test_table', 'title' => 'Comprehensive Sort Test Table'],
			[
				['test_ident' => 'name', 'title' => 'Name', 'type' => 'text'],
				['test_ident' => 'surname', 'title' => 'Surname', 'type' => 'text'],
				['test_ident' => 'age', 'title' => 'Age', 'type' => 'number'],
				['test_ident' => 'birthday', 'title' => 'Birthday', 'type' => 'datetime'],
				['test_ident' => 'department', 'title' => 'Department', 'type' => 'text'],
				['test_ident' => 'score', 'title' => 'Score', 'type' => 'number'],
				['test_ident' => 'status', 'title' => 'Status', 'type' => 'selection', 'subtype' => '', 'selection_options' => json_encode([['id' => 0, 'label' => 'Active'], ['id' => 1, 'label' => 'Inactive'], ['id' => 2, 'label' => 'Pending']])],
				['test_ident' => 'skills', 'title' => 'Skills', 'type' => 'selection', 'subtype' => 'multi', 'selection_options' => json_encode([['id' => 0, 'label' => 'PHP'], ['id' => 1, 'label' => 'JavaScript'], ['id' => 2, 'label' => 'SQL'], ['id' => 3, 'label' => 'Python'], ['id' => 4, 'label' => 'Java'], ['id' => 5, 'label' => 'React'], ['id' => 6, 'label' => 'Node.js'], ['id' => 7, 'label' => 'MongoDB'], ['id' => 8, 'label' => 'Docker'], ['id' => 9, 'label' => 'Management'], ['id' => 10, 'label' => 'Communication'], ['id' => 11, 'label' => 'Excel'], ['id' => 12, 'label' => 'Accounting'], ['id' => 13, 'label' => 'Analysis']])],
				['test_ident' => 'is_available', 'title' => 'Available', 'type' => 'selection', 'subtype' => 'check', 'selection_options' => ''],
				['test_ident' => 'experience_years', 'title' => 'Experience (Years)', 'type' => 'number']
			],
			[
				[
					'test_ident' => 'alice_row',
					'created_by' => 'user_alice',
					'created_at' => '2023-01-01 10:00:00',
					'cells' => [
						'name' => 'Alice',
						'surname' => 'Thompson-Jones',
						'age' => 28,
						'birthday' => '1995-05-15 10:30:00',
						'department' => 'IT',
						'score' => 85.5,
						'status' => 'Active',
						'skills' => ['PHP', 'JavaScript', 'SQL', 'Python'],
						'is_available' => '"true"',
						'experience_years' => 5
					]
				],
				[
					'test_ident' => 'bob_row',
					'created_by' => 'user_bob',
					'created_at' => '2023-01-02 11:00:00',
					'cells' => [
						'name' => 'Bob',
						'surname' => 'Thompson',
						'age' => 32,
						'birthday' => '1991-12-03 14:20:00',
						'department' => 'HR',
						'score' => 92.0,
						'status' => 'Inactive',
						'skills' => ['Management', 'Communication'],
						'is_available' => '"false"',
						// 'experience_years' - skipped intentionally for testing
					]
				],
				[
					'test_ident' => 'charlie_row',
					'created_by' => 'user_charlie',
					'created_at' => '2023-01-03 12:00:00',
					'cells' => [
						'name' => 'Charlie',
						'surname' => 'Wilson',
						'age' => 25,
						'birthday' => '1998-01-20 08:45:00',
						'department' => 'IT',
						'score' => 78.3,
						'status' => 'Active',
						'skills' => ['Python'],
						'is_available' => '"true"',
						'experience_years' => 2
					]
				],
				[
					'test_ident' => 'diana_row',
					'created_by' => 'user_diana',
					'created_at' => '2023-01-04 13:00:00',
					'cells' => [
						'name' => 'Diana',
						'age' => 25,
						'birthday' => '1998-08-10 16:00:00',
						'department' => 'Finance',
						'score' => 88.7,
						'status' => 'Pending',
						'skills' => ['Excel', 'Accounting', 'Analysis'],
						'is_available' => '"false"',
						'experience_years' => 3
					]
				],
				[
					'test_ident' => 'eve_row',
					'created_by' => 'user_eve',
					'created_at' => '2023-01-05 14:00:00',
					'cells' => [
						'name' => 'Eve',
						'surname' => 'Davis',
						'age' => 30,
						'birthday' => '1993-03-25 12:15:00',
						'department' => 'IT',
						'score' => 95.2,
						'status' => 'Active',
						'skills' => ['React', 'Node.js', 'MongoDB', 'Docker'],
						'experience_years' => 7
					]
				]
			]
		);

		self::$testDataResult = $result;
		self::$testTableId = $result['table']['id'];
		self::$testColumnIds = array_map(fn ($col) => $col['id'], $result['columns']);
		self::$testRowIds = array_map(fn ($row) => $row['id'], $result['rows']);
	}

	/**
	 * Data provider for sorting tests
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
				['Charlie', 'Alice', 'Diana', 'Bob', 'Eve'],  // Scores: empty, 78.3, 85.5, 88.7, 95.2
				'Sort by Score ascending'
			],
			'Number column (Score) DESC' => [
				[['columnId' => 'score', 'mode' => 'DESC']],
				['Eve', 'Bob', 'Diana', 'Alice', 'Charlie'],  // Scores: 95.2, 92.0, 88.7, 85.5, 78.3, empty
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
	 * Data provider for filtering tests
	 */
	public static function filteringDataProvider(): array {
		return [
			// Text column
			'Surname contains "son"' => [
				[[['columnId' => 'surname', 'operator' => 'contains', 'value' => 'son']]],
				['Alice', 'Bob', 'Charlie'],  // People with surnames Thompson-Jones, Thompson, Wilson
				'Filter by surname containing "son" - should include 3 rows, exclude 2'
			],
			'Surname begins with "Th"' => [
				[[['columnId' => 'surname', 'operator' => 'begins-with', 'value' => 'Th']]],
				['Alice', 'Bob'],  // People with surnames Thompson-Jones, Thompson
				'Filter by surname starting with "Th" - should include 2 rows, exclude 3'
			],
			'Surname ends with "son"' => [
				[[['columnId' => 'surname', 'operator' => 'ends-with', 'value' => 'son']]],
				['Bob', 'Charlie'],  // People with surnames Thompson, Wilson (Thompson-Jones doesn't end with "son")
				'Filter by surname ending with "son" - should include 2 rows, exclude 3'
			],
			'Surname is equal to "Thompson"' => [
				[[['columnId' => 'surname', 'operator' => 'is-equal', 'value' => 'Thompson']]],
				['Bob'],  // Person with surname Thompson
				'Filter by surname exactly equal to "Thompson" - should include 1 row, exclude 4'
			],
			'Surname is empty' => [
				[[['columnId' => 'surname', 'operator' => 'is-empty', 'value' => '']]],
				['Diana'],  // Person with empty surname
				'Filter by surname is empty - should include 1 row, exclude 4'
			],

			// Number column (Experience Years)
			'Experience years is equal to 5' => [
				[[['columnId' => 'experience_years', 'operator' => 'is-equal', 'value' => 5]]],
				['Alice'],  // Person with experience exactly 5 years
				'Filter by experience years equal to 5 - should include 1 row, exclude 4'
			],
			'Experience years is greater than 5' => [
				[[['columnId' => 'experience_years', 'operator' => 'is-greater-than', 'value' => 5]]],
				['Eve'],  // Person with experience 7 years
				'Filter by experience years greater than 5 - should include 1 rows, exclude 4'
			],
			'Experience years is greater than or equal to 5' => [
				[[['columnId' => 'experience_years', 'operator' => 'is-greater-than-or-equal', 'value' => 5]]],
				['Alice', 'Eve'],  // Person with experience 5, 7 years
				'Filter by experience years greater than or equal to 5 - should include 2 rows, exclude 3'
			],
			'Experience years is lower than 5' => [
				[[['columnId' => 'experience_years', 'operator' => 'is-lower-than', 'value' => 5]]],
				['Bob', 'Charlie', 'Diana'],  // Person with experience 2, 3 years or undefined
				'Filter by experience years lower than 5 - should include 3 rows, exclude 2'
			],
			'Experience years is lower than or equal to 5' => [
				[[['columnId' => 'experience_years', 'operator' => 'is-lower-than-or-equal', 'value' => 5]]],
				['Alice', 'Bob', 'Charlie', 'Diana'],  // Person with experience 2, 3, 5 years or undefined
				'Filter by experience years lower than or equal to 5 - should include 4 rows, exclude 1'
			],
			'Experience years is empty' => [
				[[['columnId' => 'experience_years', 'operator' => 'is-empty', 'value' => '']]],
				['Bob'],  // Person with empty experience years
				'Filter by experience years is empty - should include 1 rows, exclude 4'
			],

			// Selection-check column
			'Available is equal to true' => [
				[[['columnId' => 'is_available', 'operator' => 'is-equal', 'value' => '@checked']]],
				['Alice', 'Charlie'],  // Person with is_available = "true"
				'Filter by is_available equal to "true" - should include 2 rows, exclude 3'
			],
			'Available is equal to false' => [
				[[['columnId' => 'is_available', 'operator' => 'is-equal', 'value' => '@unchecked']]],
				['Bob', 'Diana'],  // Person with is_available = "false"
				'Filter by is_available equal to "false" - should include 2 rows, exclude 3'
			],
			'Available is empty' => [
				[[['columnId' => 'is_available', 'operator' => 'is-empty', 'value' => '']]],
				['Eve'],  // Person with empty is_available
				'Filter by is_available is empty - should include 1 row, exclude 4'
			],

			// Selection-single column (Status)
			'Status is equal to "Inactive"' => [
				[[['columnId' => 'status', 'operator' => 'is-equal', 'value' => '@selection-id-1']]],
				['Bob'],  // Person with status "Inactive" (id: 1)
				'Filter by status exactly equal to "Inactive" - should include 1 row, exclude 4'
			],
			'Status contains "Active"' => [
				[[['columnId' => 'status', 'operator' => 'contains', 'value' => '@selection-id-0']]],
				['Alice', 'Charlie', 'Eve'],  // Person with status "Active" (id: 0)
				'Filter by status containing "Active" - should include 3 rows, exclude 2'
			],
			/* currently work only frontend
			'Status contains "Active"' => [
				[[['columnId' => 'status', 'operator' => 'contains', 'value' => 'Act']]],
				['Alice', 'Charlie', 'Eve'],  // Person with status "Active" (id: 0)
				'Filter by status containing "Active" - should include 3 rows, exclude 2'
			],*/

			// Selection-multiple column (Skills)
			'Skills contains "PHP"' => [
				[[['columnId' => 'skills', 'operator' => 'contains', 'value' => '@selection-id-0']]],
				['Alice'],  // Person with skills "PHP" (id: 0)
				'Filter by skills containing "PHP" - should include 1 rows, exclude 4'
			],
			'Skills contains "Python"' => [
				[[['columnId' => 'skills', 'operator' => 'is-equal', 'value' => '@selection-id-3']]],
				['Charlie'],  // Person with skills "Python" (id: 3)
				'Filter by skills containing "Python" - should include 1 rows, exclude 4'
			],
			/* currently not work (frontend filter settings not support multiple values)
			'Skills is equal to "Management", "Communication"' => [
				[[['columnId' => 'skills', 'operator' => 'is-equal', 'value' => ['@selection-id-9', '@selection-id-10']]],
				['Bob'],  // Person with skills "Management" (id: 9) and "Communication" (id: 10)
				'Filter by skills exactly equal to "Management" and "Communication" - should include 1 rows, exclude 4'
			],*/
			/* currently work only frontend
			'Skills contains "Pyt"' => [
				[[['columnId' => 'skills', 'operator' => 'contains', 'value' => 'Pyt']]],
				['Alice', 'Charlie'],  // Person with skills "Python" (id: 3)
				'Filter by skills containing "Python" - should include 2 rows, exclude 3'
			],*/


		];
	}

	/**
	 * @dataProvider sortingDataProvider
	 */
	public function testFindAllWithVariousSorting(array $sortWithNames, array $expectedNameOrder, string $description): void {
		$this->setupRealColumnMapper(self::$testTableId);

		// Convert column names to IDs
		$sort = $this->convertColumnNamesToIds($sortWithNames);

		// Execute query with sorting
		// Check without limit/offset (full selection)
		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, null, $sort, 'test_user');
		$this->assertCount(5, $rows, "Should return all 5 rows for: $description");
		$nameColumnId = self::$testColumnIds[0]; // Name column is first
		$actualNameOrder = array_map(fn ($row) => $this->getCellValue($row, $nameColumnId), $rows);
		$this->assertEquals($expectedNameOrder, $actualNameOrder, "Failed sorting test: $description");

		// Check with limit=3, offset=2 (should return 3 last in sorted order)
		$rowsLimited = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, 3, 2, null, $sort, 'test_user');
		$this->assertCount(3, $rowsLimited, "Should return 3 rows for limit=3, offset=2: $description");
		$actualNameOrderLimited = array_map(fn ($row) => $this->getCellValue($row, $nameColumnId), $rowsLimited);
		$expectedNameOrderLimited = array_slice($expectedNameOrder, 2, 3);
		$this->assertEquals($expectedNameOrderLimited, $actualNameOrderLimited, "Failed sorting test with limit/offset: $description");
	}

	/**
	 * @dataProvider filteringDataProvider
	 */
	public function testFindAllWithVariousFilters(array $filtersWithNames, array $expectedNames, string $description): void {
		$this->setupRealColumnMapper(self::$testTableId);

		// Convert column names to IDs for each filter group
		$filters = [];
		foreach ($filtersWithNames as $filterGroup) {
			$filters[] = $this->convertFilterColumnNamesToIds($filterGroup);
		}

		// Execute query with filtering
		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, $filters, null, 'test_user');

		// Check that the correct number of rows were returned
		$this->assertCount(count($expectedNames), $rows, "Should return correct number of rows for: $description");

		// Check that the returned rows have the expected names (we filter by surname, but verify by name)
		$nameColumnId = self::$testColumnIds[0]; // Name column is first
		$actualNames = array_map(fn ($row) => $this->getCellValue($row, $nameColumnId), $rows);
		$this->assertEquals($expectedNames, $actualNames, "Failed filtering test: $description");
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
		$nameColumnId = self::$testColumnIds[0];
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

		// Use mixed array: existing column + non-existent
		$mixedSort = [
			['columnId' => self::$testColumnIds[2], 'mode' => 'ASC'],  // Age (existing)
			['columnId' => 999999, 'mode' => 'DESC'],                   // Non-existent
			['columnId' => self::$testColumnIds[0], 'mode' => 'ASC']    // Name (existing)
		];

		$rows = $this->mapper->findAll(self::$testColumnIds, self::$testTableId, null, null, null, $mixedSort, 'test_user');

		// Check that all rows were returned
		$this->assertCount(5, $rows, 'Should return all 5 rows with mixed sort array');

		// Check that sorting works only for existing columns
		// Expect sorting by Age ASC, then by Name ASC (non-existent column is ignored)
		$nameColumnId = self::$testColumnIds[0];
		$ageColumnId = self::$testColumnIds[2];

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

	/**
	 * Converts filter column names to IDs for Row2Mapper
	 */
	private function convertFilterColumnNamesToIds(array $filtersWithNames): array {
		$columnMapping = $this->extractTestIdentMapping(self::$testDataResult['columns']);

		$result = [];
		foreach ($filtersWithNames as $filterItem) {
			$columnName = $filterItem['columnId'];
			$operator = $filterItem['operator'];
			$value = $filterItem['value'];

			if ($columnName === 'created_by') {
				$result[] = ['columnId' => Column::TYPE_META_CREATED_BY, 'operator' => $operator, 'value' => $value];
			} elseif (isset($columnMapping[$columnName])) {
				$result[] = ['columnId' => $columnMapping[$columnName], 'operator' => $operator, 'value' => $value];
			} else {
				throw new \InvalidArgumentException("Unknown column name: $columnName");
			}
		}

		return $result;
	}

	private function setupRealColumnMapper(int $tableId): void {
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('*')
			->from('tables_columns')
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId)))
			->executeQuery();

		$columns = [];
		$columnTypes = [];
		while ($row = $result->fetch()) {
			$column = Column::fromRow($row);
			$columns[$row['id']] = $column;
			$columnTypes[$row['id']] = $row['type'];
		}
		$result->closeCursor();

		$this->columnMapper->method('find')
			->willReturnCallback(fn ($id) => $columns[$id] ?? throw new DoesNotExistException('test'));

		$this->columnMapper->method('preloadColumns');
		$this->columnMapper->method('getColumnTypes')->willReturn($columnTypes);
	}

	private function getCellValue($row, int $columnId) {
		$data = $row->getData();
		foreach ($data as $cell) {
			if ($cell['columnId'] === $columnId) {
				return $cell['value'];
			}
		}
		return null;
	}
}
