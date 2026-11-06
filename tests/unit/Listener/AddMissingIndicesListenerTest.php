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
	 * @return array<string, array{string}>
	 */
	public static function platformsWithTextIndicesProvider(): array {
		return [
			'MySQL' => [IDBConnection::PLATFORM_MYSQL],
			'PostgreSQL' => [IDBConnection::PLATFORM_POSTGRES],
			'SQLite' => [IDBConnection::PLATFORM_SQLITE],
		];
	}

	/**
	 * @dataProvider platformsWithTextIndicesProvider
	 */
	public function testIndexesTheValuesOfEveryCellTableButText(string $platform): void {
		$indices = $this->missingIndicesOn($platform);

		$this->assertSame('tables_row_cells_number', $indices['tables_row_cells_number_cv']['tableName']);
		$this->assertSame(['column_id', 'value'], $indices['tables_row_cells_number_cv']['columns']);
		$this->assertSame([], $indices['tables_row_cells_number_cv']['options']);
		foreach (AddMissingIndicesListener::TEXT_VALUE_INDICES as $tableName => $indexName) {
			if ($platform === IDBConnection::PLATFORM_POSTGRES && in_array($tableName, AddMissingIndicesListener::UNBOUNDED_TEXT_VALUE_TABLES, true)) {
				continue;
			}
			$this->assertSame($tableName, $indices[$indexName]['tableName']);
			$this->assertSame(['column_id', 'value'], $indices[$indexName]['columns']);
			$this->assertSame(['lengths' => [null, AddMissingIndicesListener::TEXT_VALUE_PREFIX_LENGTH]], $indices[$indexName]['options']);
		}
		$this->assertNotContains('tables_row_cells_text', array_column($indices, 'tableName'));
	}

	public function testLeavesOutTheSelectionValueIndexOnPostgreSql(): void {
		$indices = $this->missingIndicesOn(IDBConnection::PLATFORM_POSTGRES);

		$this->assertArrayNotHasKey('tables_row_cells_selection_cv', $indices);
		$this->assertArrayHasKey('tables_row_cells_datetime_cv', $indices);
		$this->assertArrayHasKey('tables_row_cells_usergroup_cv', $indices);
	}

	public function testLeavesOutTheTextValueIndicesOnOracle(): void {
		$indices = $this->missingIndicesOn(IDBConnection::PLATFORM_ORACLE);

		$this->assertArrayHasKey('tables_row_cells_number_cv', $indices);
		foreach (AddMissingIndicesListener::TEXT_VALUE_INDICES as $indexName) {
			$this->assertArrayNotHasKey($indexName, $indices);
		}
	}

	/**
	 * @return array<string, array{tableName: string, columns: string[], options: array}>
	 */
	private function missingIndicesOn(string $platform): array {
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getDatabaseProvider')->willReturn($platform);
		$event = new AddMissingIndicesEvent();

		(new AddMissingIndicesListener($connection))->handle($event);

		$indices = [];
		foreach ($event->getMissingIndices() as $index) {
			$indices[$index['indexName']] = ['tableName' => $index['tableName'], 'columns' => $index['columns'], 'options' => $index['options']];
		}
		return $indices;
	}
}
