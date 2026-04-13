<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\MultiSelectConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\SingleSelectConverter;
use PHPUnit\Framework\TestCase;

class SelectConverterTest extends TestCase {

	private array $colWithChoices;

	protected function setUp(): void {
		$this->colWithChoices = [
			'name' => 'Status',
			'typeOptions' => [
				'choices' => [
					['name' => 'Todo'],
					['name' => 'In Progress'],
					['name' => 'Done'],
				],
			],
		];
	}

	// =========================================================================
	// SingleSelectConverter
	// =========================================================================

	public function testSingleSelectGetAirtableType(): void {
		$this->assertSame('singleSelect', (new SingleSelectConverter())->getAirtableType());
	}

	public function testSingleSelectToTablesColumnType(): void {
		$report = [];
		$dto = (new SingleSelectConverter())->toTablesColumn($this->colWithChoices, $report);

		$this->assertNotNull($dto);
		$this->assertSame('selection', $dto->type);
		$this->assertSame('single', $dto->subtype);
		$this->assertEmpty($report);
	}

	public function testSingleSelectToTablesColumnEncodesOptions(): void {
		$report = [];
		$dto = (new SingleSelectConverter())->toTablesColumn($this->colWithChoices, $report);

		$options = json_decode($dto->selectionOptions, true);
		$this->assertCount(3, $options);
		$this->assertSame(1, $options[0]['id']);
		$this->assertSame('Todo', $options[0]['label']);
		$this->assertSame(2, $options[1]['id']);
		$this->assertSame('In Progress', $options[1]['label']);
		$this->assertSame(3, $options[2]['id']);
		$this->assertSame('Done', $options[2]['label']);
	}

	public function testSingleSelectToTablesColumnAlternativeOptionsKey(): void {
		$col = [
			'name' => 'Priority',
			'options' => [
				'choices' => [
					['name' => 'High'],
					['name' => 'Low'],
				],
			],
		];
		$report = [];
		$dto = (new SingleSelectConverter())->toTablesColumn($col, $report);

		$options = json_decode($dto->selectionOptions, true);
		$this->assertCount(2, $options);
	}

	public function testSingleSelectToTablesValueReturnsId(): void {
		$report = [];
		$result = (new SingleSelectConverter())->toTablesValue('In Progress', $this->colWithChoices, $report);
		$this->assertSame(2, $result);
	}

	public function testSingleSelectToTablesValueUnknownLabelReturnsNull(): void {
		$report = [];
		$result = (new SingleSelectConverter())->toTablesValue('Unknown', $this->colWithChoices, $report);
		$this->assertNull($result);
	}

	public function testSingleSelectToTablesValueNullReturnsNull(): void {
		$report = [];
		$this->assertNull((new SingleSelectConverter())->toTablesValue(null, $this->colWithChoices, $report));
	}

	public function testSingleSelectToTablesValueEmptyReturnsNull(): void {
		$report = [];
		$this->assertNull((new SingleSelectConverter())->toTablesValue('', $this->colWithChoices, $report));
	}

	// =========================================================================
	// MultiSelectConverter
	// =========================================================================

	public function testMultiSelectGetAirtableType(): void {
		$this->assertSame('multiSelect', (new MultiSelectConverter())->getAirtableType());
	}

	public function testMultiSelectToTablesColumnType(): void {
		$report = [];
		$dto = (new MultiSelectConverter())->toTablesColumn($this->colWithChoices, $report);

		$this->assertSame('selection', $dto->type);
		$this->assertSame('multi', $dto->subtype);
		$this->assertEmpty($report);
	}

	public function testMultiSelectToTablesValueMultipleLabels(): void {
		$report = [];
		$result = (new MultiSelectConverter())->toTablesValue(['Todo', 'Done'], $this->colWithChoices, $report);
		$this->assertSame([1, 3], $result);
	}

	public function testMultiSelectToTablesValueSingleLabelAsArray(): void {
		$report = [];
		$result = (new MultiSelectConverter())->toTablesValue(['In Progress'], $this->colWithChoices, $report);
		$this->assertSame([2], $result);
	}

	public function testMultiSelectToTablesValueNullReturnsNull(): void {
		$report = [];
		$this->assertNull((new MultiSelectConverter())->toTablesValue(null, $this->colWithChoices, $report));
	}

	public function testMultiSelectToTablesValueEmptyArrayReturnsNull(): void {
		$report = [];
		$this->assertNull((new MultiSelectConverter())->toTablesValue([], $this->colWithChoices, $report));
	}

	public function testMultiSelectToTablesValueAllUnknownReturnsNull(): void {
		$report = [];
		$result = (new MultiSelectConverter())->toTablesValue(['Bogus'], $this->colWithChoices, $report);
		$this->assertNull($result);
	}

	public function testMultiSelectToTablesValueMixedSkipsUnknown(): void {
		$report = [];
		$result = (new MultiSelectConverter())->toTablesValue(['Todo', 'Bogus', 'Done'], $this->colWithChoices, $report);
		$this->assertSame([1, 3], $result);
	}

	public function testMultiSelectToTablesValueStringScalarWrapped(): void {
		// Airtable may return a plain string when only one option is selected
		$report = [];
		$result = (new MultiSelectConverter())->toTablesValue('Todo', $this->colWithChoices, $report);
		$this->assertSame([1], $result);
	}
}
