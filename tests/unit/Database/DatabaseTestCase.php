<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Database;

use OC\DB\Connection;
use OC\DB\ConnectionFactory;
use OC\DB\MigrationService;
use OC\Migration\NullOutput;
use OC\SystemConfig;
use OCP\Server;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that require database operations.
 */
abstract class DatabaseTestCase extends TestCase {
	private static ?Connection $connectionStatic = null;
	protected ?Connection $connection = null;

	protected function setUp(): void {
		parent::setUp();
		if (self::$connectionStatic === null) {
			$envMode = getenv('TEST_MODE');
			if ($envMode === 'local') {
				self::$connectionStatic = $this->createInMemoryDatabase();
			} else {
				self::$connectionStatic = Server::get(Connection::class);
			}
			$this->applyMigrations();
		}
		$this->connection = self::$connectionStatic;
	}

	protected function tearDown(): void {
		$this->cleanupTablesData();
		parent::tearDown();
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
	protected function createTestTable(array $data = []): int {
		$defaultData = [
			'title' => 'Test Table',
			'ownership' => 'user1',
			'created_by' => 'user1',
			'created_at' => date('Y-m-d H:i:s'),
			'last_edit_by' => 'user1',
			'last_edit_at' => date('Y-m-d H:i:s'),
			'description' => 'Test table description'
		];

		$data = array_merge($defaultData, $data);

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_tables');

		foreach ($data as $key => $value) {
			$qb->setValue($key, $qb->createNamedParameter($value));
		}

		$qb->executeStatement();

		return (int)$this->connection->lastInsertId();
	}

	/**
	 * Creates a test column
	 */
	protected function createTestColumn(int $tableId, array $data = []): int {
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

		$data = array_merge($defaultData, $data);

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_columns');

		foreach ($data as $key => $value) {
			$qb->setValue($key, $qb->createNamedParameter($value));
		}

		$qb->executeStatement();

		return (int)$this->connection->lastInsertId();
	}

	/**
	 * Creates a test row
	 */
	protected function createTestRow(int $tableId, array $data = []): int {
		$defaultData = [
			'table_id' => $tableId,
			'created_by' => 'user1',
			'created_at' => date('Y-m-d H:i:s'),
			'last_edit_by' => 'user1',
			'last_edit_at' => date('Y-m-d H:i:s')
		];

		$data = array_merge($defaultData, $data);

		$qb = $this->connection->getQueryBuilder();
		$qb->insert('tables_rows');

		foreach ($data as $key => $value) {
			$qb->setValue($key, $qb->createNamedParameter($value));
		}

		$qb->executeStatement();

		return (int)$this->connection->lastInsertId();
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
}
