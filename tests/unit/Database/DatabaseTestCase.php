<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Database;

use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\DB\ConnectionFactory;
use OC\DB\MigrationService;
use OC\Migration\NullOutput;
use OC\SystemConfig;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Server;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that require database operations.
 */
abstract class DatabaseTestCase extends TestCase {
	private static ?Connection $connectionStatic = null;
	private static ?ConnectionAdapter $connectionAdapterStatic = null;
	protected ?Connection $connection = null;
	protected ?ConnectionAdapter $connectionAdapter = null;

	protected function setUp(): void {
		parent::setUp();
		if (self::$connectionStatic === null) {
			$envMode = getenv('TEST_MODE');
			if ($envMode === 'local') {
				self::$connectionStatic = $this->createInMemoryDatabase();
			} else {
				self::$connectionStatic = Server::get(Connection::class);
			}
			self::$connectionAdapterStatic = new ConnectionAdapter(self::$connectionStatic);

			$this->applyMigrations();
		}
		$this->connection = self::$connectionStatic;
		$this->connectionAdapter = self::$connectionAdapterStatic;
	}

	/**
	 * Creates SQLite database in memory
	 */
	private function createInMemoryDatabase(): Connection {
		// Create mock SystemConfig for unit tests (without DI container)
		$systemConfig = $this->createMock(SystemConfig::class);
		$systemConfig->method('getValue')
			->willReturnCallback(function ($key, $default = null) {
				$testConfig = [
					'dbtype' => 'sqlite',
				];
				return $testConfig[$key] ?? $default;
			});

		$connectionFactory = new ConnectionFactory($systemConfig);

		$connectionParams = [
			'path' => ':memory:',
			'tablePrefix' => 'oc_',
		];

		return $connectionFactory->getConnection('sqlite', $connectionParams);
	}

	/**
	 * Applies all migrations to the database
	 */
	private function applyMigrations(): void {
		$output = new NullOutput();

		// Use MigrationService to apply migrations
		$migrationService = new MigrationService('tables', self::$connectionStatic, $output);

		// Apply all migrations to the latest version
		$migrationService->migrate('latest');
	}

	/**
	 * Cleans up all tables_ tables for tests
	 */
	protected function cleanupTablesData(): void {
		$qb = $this->connection->getQueryBuilder();

		// Get all tables that start with 'tables_'
		$tables = $this->connection->createSchema()->getTableNames();

		$tablesToClean = array_map(function ($table) {
			$table = substr($table, strpos($table, '.') + 1);
			return str_replace($this->connection->getPrefix(), '', $table);
		}, $tables);
		$tablesToClean = array_filter($tablesToClean, function ($table) {
			return strpos($table, 'tables_') === 0;
		});

		// Sort tables by deletion priority due to foreign key constraints
		usort($tablesToClean, function ($tableA, $tableB) {
			$getPriority = function ($table) {
				// 1. row_cells tables (depend on row_sleeves and columns) - highest priority
				if (strpos($table, 'tables_row_cells_') === 0) {
					return 1;
				}
				// 2. row_sleeves (depend on tables)
				if ($table === 'tables_row_sleeves') {
					return 2;
				}
				// 3. columns (depend on tables)
				if ($table === 'tables_columns') {
					return 3;
				}
				// 4. tables (no dependencies)
				if ($table === 'tables_tables') {
					return 4;
				}
				// 5. Any other tables_ tables - lowest priority
				return 5;
			};

			return $getPriority($tableA) - $getPriority($tableB);
		});

		// Delete all tables in the sorted order
		foreach ($tablesToClean as $table) {
			$qb->delete($table)->executeStatement();
		}
	}

	/**
	 * Gets database connection
	 */
	protected function getConnection(): Connection {
		return self::$connectionStatic;
	}

	/**
	 * Creates a test table with basic data
	 */
	protected function createTestTable(array $data = []) {
		$defaultData = [
			'title' => 'Test Table',
			'ownership' => 'user1',
			'created_by' => 'user1',
			'created_at' => date('Y-m-d H:i:s'),
			'last_edit_by' => 'user1',
			'last_edit_at' => date('Y-m-d H:i:s'),
			'description' => 'Test table description'
		];

		$testIdent = $data['test_ident'] ?? null;
		unset($data['test_ident']); // Remove test_ident from data before insertion

		$data = array_merge($defaultData, $data);

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_tables');

		foreach ($data as $key => $value) {
			$qb->setValue($key, $qb->createNamedParameter($value));
		}

		$qb->executeStatement();

		$result = [
			'id' => (int)$this->connection->lastInsertId(),
		];

		if ($testIdent !== null) {
			$result['test_ident'] = $testIdent;
		}

		return $result;
	}

	/**
	 * Creates a test column
	 */
	protected function createTestColumn(int $tableId, array $data = []) {
		$defaultData = [
			'table_id' => $tableId,
			'title' => 'Test Column',
			'created_by' => 'user1',
			'created_at' => date('Y-m-d H:i:s'),
			'last_edit_by' => 'user1',
			'last_edit_at' => date('Y-m-d H:i:s'),
			'type' => 'text',
			'subtype' => '',
			'mandatory' => false,
			'order_weight' => 0,
			'number_prefix' => '',
			'number_suffix' => ''
		];

		$testIdent = $data['test_ident'] ?? null;
		unset($data['test_ident']); // Remove test_ident from data before insertion

		$data = array_merge($defaultData, $data);

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_columns');

		foreach ($data as $key => $value) {
			if ($key === 'mandatory') {
				$qb->setValue($key, $qb->createNamedParameter($value, IQueryBuilder::PARAM_BOOL));
			} else {
				$qb->setValue($key, $qb->createNamedParameter($value));
			}
		}

		$qb->executeStatement();

		$result = [
			'id' => (int)$this->connection->lastInsertId(),
		];

		if ($testIdent !== null) {
			$result['test_ident'] = $testIdent;
		}

		return $result;
	}

	/**
	 * Creates a test row sleeve
	 */
	protected function createTestRow(int $tableId, array $data = []) {
		$defaultData = [
			'table_id' => $tableId,
			'created_by' => 'user1',
			'created_at' => date('Y-m-d H:i:s'),
			'last_edit_by' => 'user1',
			'last_edit_at' => date('Y-m-d H:i:s')
		];

		$testIdent = $data['test_ident'] ?? null;
		unset($data['test_ident']); // Remove test_ident from data before insertion

		$data = array_merge($defaultData, $data);

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_row_sleeves');

		foreach ($data as $key => $value) {
			$qb->setValue($key, $qb->createNamedParameter($value));
		}

		$qb->executeStatement();

		$result = [
			'id' => (int)$this->connection->lastInsertId(),
		];

		if ($testIdent !== null) {
			$result['test_ident'] = $testIdent;
		}

		return $result;
	}

	/**
	 * Checks if a table exists in the database
	 */
	protected function tableExists(string $tableName): bool {
		$schema = $this->connection->createSchema();
		return $schema->hasTable($this->connection->getPrefix() . $tableName);
	}

	/**
	 * Gets the number of records in a table
	 */
	protected function getTableCount(string $tableName): int {
		$qb = $this->connection->getQueryBuilder();
		return (int)$qb->select($qb->func()->count('*'))
			->from($tableName)
			->executeQuery()
			->fetchOne();
	}

	/**
	 * Creates a test row with cell data
	 */
	protected function createTestRowWithData(int $tableId, array $rowData = [], array $cellsData = [], array $columnMapping = []) {
		$result = $this->createTestRow($tableId, $rowData);

		// Extract the actual row ID from the result
		$rowId = $result['id'];

		if (!empty($cellsData)) {
			$this->addCellsToRow($rowId, $cellsData, $columnMapping);
		}

		return $result;
	}

	/**
	 * Adds cell data to an existing row
	 */
	protected function addCellsToRow(int $rowId, array $cellsData, array $columnMapping = []): void {
		foreach ($cellsData as $columnIdentifier => $value) {
			// Convert test_ident to actual column ID if mapping is provided
			if (is_string($columnIdentifier) && isset($columnMapping[$columnIdentifier])) {
				$columnId = $columnMapping[$columnIdentifier];
			} else {
				$columnId = $columnIdentifier;
			}

			$this->insertCellData($rowId, $columnId, $value);
		}
	}

	/**
	 * Inserts cell data into appropriate table based on column type
	 */
	protected function insertCellData(int $rowId, int $columnId, $value): void {
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select('type')
			->from('tables_columns')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($columnId)))
			->executeQuery();

		$columnType = $result->fetchOne();
		$result->closeCursor();

		if (!$columnType) {
			throw new \InvalidArgumentException("Column with ID $columnId not found");
		}

		$this->insertCellIntoTypeTable($rowId, $columnId, $value, $columnType);
	}

	/**
	 * Inserts cell data into the appropriate type-specific table
	 */
	protected function insertCellIntoTypeTable(int $rowId, int $columnId, $value, string $columnType): void {
		$tableName = 'tables_row_cells_' . $columnType;

		$qb = $this->connection->getQueryBuilder();
		$qb->insert($tableName)
			->setValue('row_id', $qb->createNamedParameter($rowId))
			->setValue('column_id', $qb->createNamedParameter($columnId))
			->setValue('value', $qb->createNamedParameter($value))
			->setValue('last_edit_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->setValue('last_edit_by', $qb->createNamedParameter('user1'));

		$qb->executeStatement();
	}

	/**
	 * Creates a complete test table with columns and rows with data
	 */
	protected function createCompleteTestTable(array $tableData = [], array $columnsData = [], array $rowsData = []): array {
		$tableResult = $this->createTestTable($tableData);
		$tableId = $tableResult['id'];

		$columnResults = [];
		foreach ($columnsData as $columnData) {
			$columnResult = $this->createTestColumn($tableId, $columnData);
			$columnResults[] = $columnResult;
		}

		// Create column mapping for test_ident -> id conversion
		$columnMapping = [];
		foreach ($columnResults as $columnResult) {
			if (isset($columnResult['test_ident'])) {
				$columnMapping[$columnResult['test_ident']] = $columnResult['id'];
			}
		}

		$rowResults = [];
		foreach ($rowsData as $rowData) {
			$cellsData = $rowData['cells'] ?? [];
			unset($rowData['cells']);
			$rowResult = $this->createTestRowWithData($tableId, $rowData, $cellsData, $columnMapping);
			$rowResults[] = $rowResult;
		}

		$result = [
			'table' => $tableResult,
			'columns' => $columnResults,
			'rows' => $rowResults
		];

		return $result;
	}

	/**
	 * Extracts test_ident -> id mapping from creation results
	 * @param array $results Array of creation results
	 * @return array Mapping of test_ident => id
	 */
	protected function extractTestIdentMapping(array $results): array {
		$mapping = [];
		foreach ($results as $result) {
			if (isset($result['test_ident'])) {
				$mapping[$result['test_ident']] = $result['id'];
			}
		}
		return $mapping;
	}

	/**
	 * Gets ID by test_ident from creation results
	 * @param array $results Array of creation results
	 * @param string $testIdent Test identifier to find
	 * @return int|null Returns ID if found, null otherwise
	 */
	protected function getIdByTestIdent(array $results, string $testIdent): ?int {
		foreach ($results as $result) {
			if (isset($result['test_ident']) && $result['test_ident'] === $testIdent) {
				return $result['id'];
			}
		}
		return null;
	}
}
