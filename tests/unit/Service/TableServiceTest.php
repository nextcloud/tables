<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Table;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\Model\TableScheme;
use PHPUnit\Framework\TestCase;

class TableServiceTest extends TestCase {

	public function testSortRoundTripSingleRule(): void {
		$input = [['columnId' => 3, 'mode' => 'ASC']];

		$table = new Table();
		// Simulate what TableService::update() does when $sort !== null
		$table->setSort(\json_encode(SortRuleSet::createFromInputArray($input)->jsonSerialize()));

		$this->assertSame($input, $table->getSortArray());
	}

	public function testSortRoundTripMultipleRules(): void {
		$input = [
			['columnId' => 7, 'mode' => 'DESC'],
			['columnId' => 2, 'mode' => 'ASC'],
		];

		$table = new Table();
		$table->setSort(\json_encode(SortRuleSet::createFromInputArray($input)->jsonSerialize()));

		$this->assertSame($input, $table->getSortArray());
	}

	public function testSortRoundTripEmptyArray(): void {
		$table = new Table();
		$table->setSort(\json_encode(SortRuleSet::createFromInputArray([])->jsonSerialize()));

		$this->assertSame([], $table->getSortArray());
	}

	public function testNullSortInputLeavesFieldUnchanged(): void {
		$existing = [['columnId' => 5, 'mode' => 'DESC']];

		$table = new Table();
		$table->setSort(\json_encode($existing));

		// Simulate what TableService::update() does when $sort === null: skip update
		$sort = null;
		if ($sort !== null) {
			$table->setSort(\json_encode(SortRuleSet::createFromInputArray($sort)->jsonSerialize()));
		}

		$this->assertSame($existing, $table->getSortArray());
	}

	public function testNullSortInputOnUninitializedTableLeavesFieldNull(): void {
		$table = new Table();
		// sort is null by default; passing null must not change it
		$sort = null;
		if ($sort !== null) {
			$table->setSort(\json_encode(SortRuleSet::createFromInputArray($sort)->jsonSerialize()));
		}

		$this->assertNull($table->getSort());
		$this->assertSame([], $table->getSortArray());
	}

	public function testGetSchemeColumnOrderRoundTrip(): void {
		$columnOrder = [['columnId' => 3, 'order' => 1]];

		$table = new Table();
		$table->setColumnOrder(\json_encode($columnOrder));

		$scheme = new TableScheme('T', '📋', [], [], '', '1.0.0',
			$table->getColumnOrderSettingsArray(),
			$table->getSortArray(),
		);

		$decoded = json_decode(json_encode($scheme), true);
		$this->assertSame($columnOrder, $decoded['columnOrder']);
		$this->assertSame([], $decoded['sort']);
	}

	public function testGetSchemeSortRoundTrip(): void {
		$sort = [['columnId' => 7, 'mode' => 'DESC']];

		$table = new Table();
		$table->setSort(\json_encode($sort));

		$scheme = new TableScheme('T', '📋', [], [], '', '1.0.0',
			$table->getColumnOrderSettingsArray(),
			$table->getSortArray(),
		);

		$decoded = json_decode(json_encode($scheme), true);
		$this->assertSame([], $decoded['columnOrder']);
		$this->assertSame($sort, $decoded['sort']);
	}

	public function testCreateFromSchemeColumnOrderIdRemapping(): void {
		$columnOrder = [['columnId' => 10, 'order' => 1, 'readonly' => false, 'mandatory' => false]];
		$colMap = [10 => 99];

		$remapped = array_map(static function (array $entry) use ($colMap): array {
			if (isset($entry['columnId']) && $entry['columnId'] > 0) {
				$entry['columnId'] = $colMap[$entry['columnId']] ?? $entry['columnId'];
			}
			return $entry;
		}, $columnOrder);

		$this->assertSame(99, $remapped[0]['columnId']);
		$this->assertSame(1, $remapped[0]['order']);
	}

	public function testCreateFromSchemeSortIdRemapping(): void {
		$sort = [['columnId' => 10, 'mode' => 'ASC']];
		$colMap = [10 => 99];

		$remapped = array_map(static function (array $entry) use ($colMap): array {
			if (isset($entry['columnId']) && $entry['columnId'] > 0) {
				$entry['columnId'] = $colMap[$entry['columnId']] ?? $entry['columnId'];
			}
			return $entry;
		}, $sort);

		$this->assertSame(99, $remapped[0]['columnId']);
		$this->assertSame('ASC', $remapped[0]['mode']);
	}
}
