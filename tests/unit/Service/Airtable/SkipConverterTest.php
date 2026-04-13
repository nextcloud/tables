<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\AiTextSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\AttachmentSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\AutoNumberConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\BarcodeConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\ButtonSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\CountSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\ExternalSyncSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\ForeignKeySkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\FormulaSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\LookupSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\RollupSkipConverter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all skip-and-report converters.
 *
 * Every skip converter must:
 *  - Return the correct Airtable type string from getAirtableType()
 *  - Return null from toTablesColumn() (no column created)
 *  - Append exactly one report row to $reportRows
 *  - Return null from toTablesValue() regardless of input
 */
class SkipConverterTest extends TestCase {

	// =========================================================================
	// AbstractSkipConverter contract
	// =========================================================================

	/** @return array<string, array{object, string}> */
	public function skipConverterProvider(): array {
		return [
			'attachment'   => [new AttachmentSkipConverter(),   'multipleAttachments'],
			'foreignKey'   => [new ForeignKeySkipConverter(),   'foreignKey'],
			'formula'      => [new FormulaSkipConverter(),      'formula'],
			'lookup'       => [new LookupSkipConverter(),       'lookup'],
			'rollup'       => [new RollupSkipConverter(),       'rollup'],
			'count'        => [new CountSkipConverter(),        'count'],
			'button'       => [new ButtonSkipConverter(),       'button'],
			'externalSync' => [new ExternalSyncSkipConverter(), 'externalSyncSource'],
			'aiText'       => [new AiTextSkipConverter(),       'aiText'],
		];
	}

	/** @dataProvider skipConverterProvider */
	public function testGetAirtableType(object $converter, string $expectedType): void {
		$this->assertSame($expectedType, $converter->getAirtableType());
	}

	/** @dataProvider skipConverterProvider */
	public function testToTablesColumnReturnsNull(object $converter, string $expectedType): void {
		$col = ['name' => 'Test field', 'type' => $expectedType];
		$report = [];

		$result = $converter->toTablesColumn($col, $report);

		$this->assertNull($result, "toTablesColumn() must return null for {$expectedType}");
	}

	/** @dataProvider skipConverterProvider */
	public function testToTablesColumnAppendsOneReportRow(object $converter, string $expectedType): void {
		$col = ['name' => 'My field', 'type' => $expectedType];
		$report = [];

		$converter->toTablesColumn($col, $report);

		$this->assertCount(1, $report, "Expected exactly one report row for {$expectedType}");
		$this->assertSame('My field', $report[0]['object_name']);
		$this->assertSame($expectedType, $report[0]['airtable_type']);
		$this->assertSame('field', $report[0]['object_type']);
		$this->assertNotEmpty($report[0]['reason']);
	}

	/** @dataProvider skipConverterProvider */
	public function testToTablesValueReturnsNull(object $converter, string $expectedType): void {
		$col = ['name' => 'Test field'];
		$report = [];

		$this->assertNull(
			$converter->toTablesValue('some value', $col, $report),
			"toTablesValue() must return null for {$expectedType}"
		);
	}

	// =========================================================================
	// FormulaSkipConverter — includes formula expression in reason
	// =========================================================================

	public function testFormulaReasonIncludesExpression(): void {
		$col = [
			'name' => 'Full name',
			'type' => 'formula',
			'typeOptions' => ['formula' => '{First} & " " & {Last}'],
		];
		$report = [];

		(new FormulaSkipConverter())->toTablesColumn($col, $report);

		$this->assertStringContainsString('{First} & " " & {Last}', $report[0]['reason']);
	}

	public function testFormulaReasonFallsBackToTextParsed(): void {
		$col = [
			'name' => 'Full name',
			'typeOptions' => ['formulaTextParsed' => 'CONCATENATE(First, Last)'],
		];
		$report = [];

		(new FormulaSkipConverter())->toTablesColumn($col, $report);

		$this->assertStringContainsString('CONCATENATE(First, Last)', $report[0]['reason']);
	}

	public function testFormulaReasonOmitsExpressionWhenAbsent(): void {
		$col = ['name' => 'Calc', 'typeOptions' => []];
		$report = [];

		(new FormulaSkipConverter())->toTablesColumn($col, $report);

		$this->assertStringNotContainsString('Original formula:', $report[0]['reason']);
	}

	// =========================================================================
	// AutoNumberConverter — custom (not AbstractSkipConverter), same contract
	// =========================================================================

	public function testAutoNumberGetAirtableType(): void {
		$this->assertSame('autoNumber', (new AutoNumberConverter())->getAirtableType());
	}

	public function testAutoNumberToTablesColumnReturnsNull(): void {
		$col = ['name' => 'Row ID'];
		$report = [];

		$this->assertNull((new AutoNumberConverter())->toTablesColumn($col, $report));
		$this->assertCount(1, $report);
		$this->assertSame('autoNumber', $report[0]['airtable_type']);
	}

	public function testAutoNumberToTablesValueReturnsNull(): void {
		$report = [];
		$this->assertNull((new AutoNumberConverter())->toTablesValue(42, [], $report));
	}

	// =========================================================================
	// BarcodeConverter — custom (not AbstractSkipConverter)
	// =========================================================================

	public function testBarcodeGetAirtableType(): void {
		$this->assertSame('barcode', (new BarcodeConverter())->getAirtableType());
	}

	public function testBarcodeToTablesColumnReturnsTextColumn(): void {
		$col = ['name' => 'Barcode'];
		$report = [];
		$dto = (new BarcodeConverter())->toTablesColumn($col, $report);

		// Barcode is lossy — returns a text/line column + one report row
		$this->assertNotNull($dto);
		$this->assertSame('text', $dto->type);
		$this->assertSame('line', $dto->subtype);
		$this->assertCount(1, $report);
		$this->assertSame('barcode', $report[0]['airtable_type']);
	}

	public function testBarcodeToTablesValueExtractsText(): void {
		$report = [];
		$result = (new BarcodeConverter())->toTablesValue(['text' => '12345', 'type' => 'upce'], [], $report);
		$this->assertSame('12345', $result);
	}

	public function testBarcodeToTablesValueStringPassthrough(): void {
		$report = [];
		$result = (new BarcodeConverter())->toTablesValue('9780201379624', [], $report);
		$this->assertSame('9780201379624', $result);
	}

	public function testBarcodeToTablesValueNullReturnsNull(): void {
		$report = [];
		$this->assertNull((new BarcodeConverter())->toTablesValue(null, [], $report));
	}
}
