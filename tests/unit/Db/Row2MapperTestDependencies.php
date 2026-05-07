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
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Trait providing test dependencies and utilities for Row2Mapper testing
 *
 * This trait sets up mock objects, test data, and helper methods needed
 * for comprehensive testing of the Row2Mapper class functionality.
 */

trait Row2MapperTestDependencies {
	protected Row2Mapper $mapper;
	protected ColumnMapper|MockObject $columnMapper;
	protected RowSleeveMapper $rowSleeveMapper;
	protected UserHelper|MockObject $userHelper;
	protected ColumnsHelper|MockObject $columnsHelper;
	protected LoggerInterface|MockObject $logger;
	protected CircleHelper|MockObject $circleHelper;

	protected static bool $testDataInitialized = false;
	protected static int $testTableId;
	protected static array $testColumnIds = [];
	protected static array $testRowIds = [];
	protected static array $testDataResult = [];

	/**
	 * Sets up all required dependencies and mock objects for Row2Mapper testing
	 *
	 * Initializes mock objects for external dependencies and creates real instances
	 * of helper classes. Also ensures test data is initialized only once.
	 */

	protected function setupDependencies(): void {
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

	/**
	 * Initializes comprehensive test data for sorting and querying tests
	 *
	 * Creates a complete test table with multiple columns of different types
	 * and sample rows with various data values for comprehensive testing.
	 */
	private function initializeTestData(): void {
		$result = $this->createCompleteTestTable(
			['test_ident' => 'sort_test_table', 'title' => 'Comprehensive Sort Test Table'],
			[
				['test_ident' => 'name', 'title' => 'Name', 'type' => 'text'],
				['test_ident' => 'age', 'title' => 'Age', 'type' => 'number'],
				['test_ident' => 'birthday', 'title' => 'Birthday', 'type' => 'datetime'],
				['test_ident' => 'department', 'title' => 'Department', 'type' => 'text'],
				['test_ident' => 'score', 'title' => 'Score', 'type' => 'number']
			],
			[
				[
					'test_ident' => 'alice_row',
					'created_by' => 'user_alice',
					'created_at' => '2023-01-01 10:00:00',
					'cells' => [
						'name' => 'Alice',
						'age' => 28,
						'birthday' => '1995-05-15 10:30:00',
						'department' => 'IT',
						'score' => 85.5
					]
				],
				[
					'test_ident' => 'bob_row',
					'created_by' => 'user_bob',
					'created_at' => '2023-01-02 11:00:00',
					'cells' => [
						'name' => 'Bob',
						'age' => 32,
						'birthday' => '1991-12-03 14:20:00',
						'department' => 'HR',
						'score' => 92.0
					]
				],
				[
					'test_ident' => 'charlie_row',
					'created_by' => 'user_charlie',
					'created_at' => '2023-01-03 12:00:00',
					'cells' => [
						'name' => 'Charlie',
						'age' => 25,
						'birthday' => '1998-01-20 08:45:00',
						'department' => 'IT',
						'score' => 78.3
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
						'score' => 88.7
					]
				],
				[
					'test_ident' => 'eve_row',
					'created_by' => 'user_eve',
					'created_at' => '2023-01-05 14:00:00',
					'cells' => [
						'name' => 'Eve',
						'age' => 30,
						'birthday' => '1993-03-25 12:15:00',
						'department' => 'IT',
						'score' => 95.2
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
	 * Sets up a real ColumnMapper with actual column data from the database
	 *
	 * Instead of using mocked column data, this method loads real column
	 * information from the database for more realistic testing scenarios.
	 *
	 * @param int $tableId The ID of the table to load columns for
	 */
	protected function setupRealColumnMapper(int $tableId): void {
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('id', 'title', 'type', 'table_id')
			->from('tables_columns')
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId)))
			->executeQuery();

		$columns = [];
		$columnTypes = [];
		while ($row = $result->fetch()) {
			$column = new Column();
			$column->setId($row['id']);
			$column->setTitle($row['title']);
			$column->setType($row['type']);
			$column->setTableId($row['table_id']);
			$columns[$row['id']] = $column;
			$columnTypes[$row['id']] = $row['type'];
		}
		$result->closeCursor();

		$this->columnMapper->method('find')
			->willReturnCallback(fn ($id) => $columns[$id] ?? throw new DoesNotExistException('test'));

		$this->columnMapper->method('preloadColumns');
		$this->columnMapper->method('getColumnTypes')->willReturn($columnTypes);
	}

	/**
	 * Extracts the value of a specific cell from a Row object
	 *
	 * Searches through the row's data array to find the cell with the
	 * specified column ID and returns its value.
	 *
	 * @param mixed $row The Row object containing cell data
	 * @param int $columnId The ID of the column to get the value for
	 * @return mixed The cell value or null if not found
	 */
	protected function getCellValue($row, int $columnId) {
		$data = $row->getData();
		foreach ($data as $cell) {
			if ($cell['columnId'] === $columnId) {
				return $cell['value'] ?? '';
			}
		}
		return '';
	}

	/**
	 * Helper method: Creates mapping from test identifiers to column IDs
	 *
	 * Extracts test_ident values from column definitions and creates
	 * a lookup array for easier test assertions and data access.
	 *
	 * @param array $columns Array of column definitions with test_ident keys
	 * @return array Associative array mapping test_ident to column ID
	 */
	protected function extractTestIdentMapping(array $columns): array {
		$mapping = [];
		foreach ($columns as $column) {
			if (isset($column['test_ident'])) {
				$mapping[$column['test_ident']] = $column['id'];
			}
		}
		return $mapping;
	}
}
