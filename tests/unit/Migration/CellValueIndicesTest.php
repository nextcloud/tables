<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\Tables\Listener\AddMissingIndicesListener;
use OCA\Tables\Migration\Version000700Date20230916000000;
use OCA\Tables\Migration\Version000800Date20240712000000;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\ITable;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Fresh installations get the cell value indices from the migrations that create the cell tables,
 * existing installations from AddMissingIndicesListener. Both must define the same indices: as
 * occ db:add-missing-indices compares by name, a drifted name gives fresh installations a second
 * index and a drifted definition gives them a different one than upgraded installations.
 */
class CellValueIndicesTest extends TestCase {
	/**
	 * @return array<string, array{string}>
	 */
	public static function platformsProvider(): array {
		return [
			'MySQL' => [IDBConnection::PLATFORM_MYSQL],
			'PostgreSQL' => [IDBConnection::PLATFORM_POSTGRES],
			'SQLite' => [IDBConnection::PLATFORM_SQLITE],
			'Oracle' => [IDBConnection::PLATFORM_ORACLE],
		];
	}

	/**
	 * @dataProvider platformsProvider
	 */
	public function testMigrationsCreateTheIndicesTheListenerAdds(string $platform): void {
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getDatabaseProvider')->willReturn($platform);

		$this->assertSame($this->valueIndicesFromListener($connection), $this->valueIndicesFromMigrations($connection));
	}

	/**
	 * @return array<string, array{tableName: string, columns: string[], options: array}>
	 */
	private function valueIndicesFromListener(IDBConnection $connection): array {
		$event = new AddMissingIndicesEvent();
		(new AddMissingIndicesListener($connection))->handle($event);

		$indices = [];
		foreach ($event->getMissingIndices() as $index) {
			if (str_ends_with($index['indexName'], '_cv')) {
				$indices[$index['indexName']] = ['tableName' => $index['tableName'], 'columns' => $index['columns'], 'options' => $index['options']];
			}
		}
		ksort($indices);
		return $indices;
	}

	/**
	 * @return array<string, array{tableName: string, columns: string[], options: array}>
	 */
	private function valueIndicesFromMigrations(IDBConnection $connection): array {
		$indices = [];
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(false);
		$schema->method('createTable')->willReturnCallback(function (string $tableName) use (&$indices) {
			$table = $this->createMock(interface_exists(ITable::class) ? ITable::class : Table::class);
			$table->method('addIndex')->willReturnCallback(
				function (array $columns, ?string $indexName = null, array $flags = [], array $options = []) use (&$indices, $tableName, $table) {
					if ($indexName !== null && str_ends_with($indexName, '_cv')) {
						$indices[$indexName] = ['tableName' => $tableName, 'columns' => $columns, 'options' => $options];
					}
					return $table;
				}
			);
			return $table;
		});
		$output = $this->createMock(IOutput::class);

		(new Version000700Date20230916000000($connection))->changeSchema($output, static fn () => $schema, []);
		(new Version000800Date20240712000000($connection))->changeSchema($output, static fn () => $schema, []);

		ksort($indices);
		return $indices;
	}
}
