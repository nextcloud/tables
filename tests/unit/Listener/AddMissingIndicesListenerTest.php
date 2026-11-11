<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Tables\Listener\AddMissingIndicesListener;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class AddMissingIndicesListenerTest extends TestCase {
	/**
	 * @return array<string, array{string, array<string, array{tableName: string, columns: string[], options: array}>}>
	 */
	public static function valueIndicesPerPlatformProvider(): array {
		$number = ['tableName' => 'tables_row_cells_number', 'columns' => ['column_id', 'value'], 'options' => []];
		$datetime = ['tableName' => 'tables_row_cells_datetime', 'columns' => ['column_id', 'value'], 'options' => ['lengths' => [null, 64]]];
		$selection = ['tableName' => 'tables_row_cells_selection', 'columns' => ['column_id', 'value'], 'options' => ['lengths' => [null, 64]]];
		$usergroup = ['tableName' => 'tables_row_cells_usergroup', 'columns' => ['column_id', 'value'], 'options' => ['lengths' => [null, 64]]];
		$allFour = [
			'tables_row_cells_datetime_cv' => $datetime,
			'tables_row_cells_number_cv' => $number,
			'tables_row_cells_selection_cv' => $selection,
			'tables_row_cells_usergroup_cv' => $usergroup,
		];

		return [
			'MySQL' => [IDBConnection::PLATFORM_MYSQL, $allFour],
			'SQLite' => [IDBConnection::PLATFORM_SQLITE, $allFour],
			'PostgreSQL' => [IDBConnection::PLATFORM_POSTGRES, [
				'tables_row_cells_datetime_cv' => $datetime,
				'tables_row_cells_number_cv' => $number,
				'tables_row_cells_usergroup_cv' => $usergroup,
			]],
			'Oracle' => [IDBConnection::PLATFORM_ORACLE, [
				'tables_row_cells_number_cv' => $number,
			]],
		];
	}

	/**
	 * @dataProvider valueIndicesPerPlatformProvider
	 * @param array<string, array{tableName: string, columns: string[], options: array}> $expected
	 */
	public function testAddsExactlyTheValueIndicesOfThePlatform(string $platform, array $expected): void {
		$this->assertSame($expected, $this->valueIndicesOn($platform));
	}

	/**
	 * @return array<string, array{tableName: string, columns: string[], options: array}>
	 */
	private function valueIndicesOn(string $platform): array {
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getDatabaseProvider')->willReturn($platform);
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
}
