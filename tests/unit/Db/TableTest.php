<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase {

	private function makeTable(?string $columnOrder): Table {
		$table = new Table();
		$table->setColumnOrder($columnOrder);
		return $table;
	}

	public function testGetColumnOrderSettingsArrayNullReturnsEmpty(): void {
		$table = $this->makeTable(null);
		$this->assertSame([], $table->getColumnOrderSettingsArray());
	}

	public function testGetColumnOrderSettingsArrayEmptyStringReturnsEmpty(): void {
		$table = $this->makeTable('');
		$this->assertSame([], $table->getColumnOrderSettingsArray());
	}

	public function testGetColumnOrderSettingsArrayLegacyPlainIdFormat(): void {
		// Legacy format: plain array of integer column IDs — order is derived from index (1-based)
		$table = $this->makeTable(\json_encode([10, 20, 30]));

		$result = $table->getColumnOrderSettingsArray();

		$this->assertCount(3, $result);
		$this->assertSame(10, $result[0]->getId());
		$this->assertSame(1, $result[0]->getOrder());
		$this->assertSame(20, $result[1]->getId());
		$this->assertSame(2, $result[1]->getOrder());
		$this->assertSame(30, $result[2]->getId());
		$this->assertSame(3, $result[2]->getOrder());
	}

	public function testGetColumnOrderSettingsArrayObjectFormat(): void {
		// New format: array of ViewColumnInformation-shaped objects
		$json = \json_encode([
			['columnId' => 5, 'order' => 2, 'readonly' => false, 'mandatory' => false],
			['columnId' => 3, 'order' => 1, 'readonly' => false, 'mandatory' => false],
		]);
		$table = $this->makeTable($json);

		$result = $table->getColumnOrderSettingsArray();

		$this->assertCount(2, $result);
		// Returns in stored order (not re-sorted)
		$this->assertSame(5, $result[0]->getId());
		$this->assertSame(2, $result[0]->getOrder());
		$this->assertSame(3, $result[1]->getId());
		$this->assertSame(1, $result[1]->getOrder());
	}

	public function testGetColumnOrderArrayNullReturnsEmpty(): void {
		$table = $this->makeTable(null);
		$this->assertSame([], $table->getColumnOrderArray());
	}

	public function testGetColumnOrderArraySortsByOrder(): void {
		// Stored with non-sequential order values — must come back sorted ascending by order
		$json = \json_encode([
			['columnId' => 7, 'order' => 3, 'readonly' => false, 'mandatory' => false],
			['columnId' => 4, 'order' => 1, 'readonly' => false, 'mandatory' => false],
			['columnId' => 9, 'order' => 2, 'readonly' => false, 'mandatory' => false],
		]);
		$table = $this->makeTable($json);

		$this->assertSame([4, 9, 7], $table->getColumnOrderArray());
	}

	public function testGetColumnOrderArrayLegacyFormat(): void {
		// Legacy plain-ID format — order assigned 1,2,3 from index, so result must preserve insertion order
		$table = $this->makeTable(\json_encode([10, 20, 30]));

		$this->assertSame([10, 20, 30], $table->getColumnOrderArray());
	}

	private function makeSortTable(?string $sort): Table {
		$table = new Table();
		$table->setSort($sort);
		return $table;
	}

	public function testGetSortArrayNullReturnsEmpty(): void {
		$table = $this->makeSortTable(null);
		$this->assertSame([], $table->getSortArray());
	}

	public function testGetSortArrayEmptyStringReturnsEmpty(): void {
		$table = $this->makeSortTable('');
		$this->assertSame([], $table->getSortArray());
	}

	public function testGetSortArrayDeserializesSingleRule(): void {
		$table = $this->makeSortTable(\json_encode([['columnId' => 3, 'mode' => 'ASC']]));

		$result = $table->getSortArray();

		$this->assertCount(1, $result);
		$this->assertSame(3, $result[0]['columnId']);
		$this->assertSame('ASC', $result[0]['mode']);
	}

	public function testGetSortArrayDeserializesMultipleRules(): void {
		$table = $this->makeSortTable(\json_encode([
			['columnId' => 7, 'mode' => 'DESC'],
			['columnId' => 2, 'mode' => 'ASC'],
		]));

		$result = $table->getSortArray();

		$this->assertCount(2, $result);
		$this->assertSame(7, $result[0]['columnId']);
		$this->assertSame('DESC', $result[0]['mode']);
		$this->assertSame(2, $result[1]['columnId']);
		$this->assertSame('ASC', $result[1]['mode']);
	}
}
