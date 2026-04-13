<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Service\Airtable\AirtableColumnTypeRegistry;
use OCA\Tables\Service\Airtable\ColumnTypes\AiTextSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\AttachmentSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\AutoNumberConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\BarcodeConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\ButtonSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\CheckboxConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\CollaboratorConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\CountSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\CreatedByConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\CreatedTimeConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\CurrencyConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\DateConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\DateTimeConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\DurationConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\EmailConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\ExternalSyncSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\ForeignKeySkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\FormulaSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\LastModifiedByConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\LastModifiedTimeConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\LookupSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\MultilineTextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\MultipleCollaboratorsConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\MultiSelectConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\NumberConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\PercentConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\PhoneConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\RatingConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\RichTextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\RollupSkipConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\SingleLineTextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\SingleSelectConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\TextConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\UrlConverter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration test for the full Airtable import conversion pipeline.
 *
 * Exercises the AirtableColumnTypeRegistry + all Phase 0 converters together
 * against a realistic fixture schema JSON (no live Airtable calls, no DB writes).
 *
 * Coverage:
 *  - All 20+ column types present in the fixture are resolved via the registry.
 *  - Schema conversion: toTablesColumn() returns the correct ColumnDto type/subtype/options.
 *  - Value conversion:  toTablesValue() maps fixture row values to the expected Tables format.
 *  - Report rows:       Lossy and skip-and-report converters emit exactly one report row each.
 *  - Clean import:      Lossless converters emit no report rows.
 *  - Unknown type:      The registry returns null for a type that was never registered.
 */
class AirtableImportPipelineTest extends TestCase {

	private AirtableColumnTypeRegistry $registry;
	/** @var array<string, array{id: string, name: string, type: string, typeOptions?: array}> keyed by field id */
	private array $columns;
	/** @var array<string, mixed> The first fixture row's cellValuesByColumnId */
	private array $row1;
	/** @var array<string, mixed> The second fixture row's cellValuesByColumnId */
	private array $row2;

	protected function setUp(): void {
		$logger = new NullLogger();
		$this->registry = new AirtableColumnTypeRegistry($logger);

		// Register every Phase 0 converter — mirrors what Application::boot() will do.
		foreach ($this->allConverters() as $converter) {
			$this->registry->register($converter);
		}

		// Load the fixture schema.
		$fixture = json_decode(
			file_get_contents(__DIR__ . '/fixtures/airtable_schema.json'),
			true,
		);
		$this->assertIsArray($fixture, 'Fixture file must be valid JSON');

		// Index columns by their Airtable field ID.
		$this->columns = [];
		foreach ($fixture['tableSchemas'][0]['columns'] as $col) {
			$this->columns[$col['id']] = $col;
		}

		$this->row1 = $fixture['rows'][0]['cellValuesByColumnId'];
		$this->row2 = $fixture['rows'][1]['cellValuesByColumnId'];
	}

	// =========================================================================
	// Registry
	// =========================================================================

	public function testAllPhase0TypesAreRegistered(): void {
		$expected = [
			'text', 'singleLineText', 'multilineText', 'richText', 'url', 'email', 'phone',
			'number', 'currency', 'percent', 'rating', 'duration',
			'checkbox',
			'date', 'dateTime', 'createdTime', 'lastModifiedTime',
			'singleSelect', 'multiSelect',
			'singleCollaborator', 'multipleCollaborators', 'createdBy', 'lastModifiedBy',
			'barcode', 'autoNumber',
			'formula', 'foreignKey', 'lookup', 'rollup', 'count', 'button',
			'multipleAttachments', 'aiText',
		];

		foreach ($expected as $type) {
			$this->assertTrue(
				$this->registry->has($type),
				"Registry must have a converter for '{$type}'"
			);
		}
	}

	public function testUnknownTypeReturnsNull(): void {
		$this->assertNull($this->registry->get('neverRegisteredType'));
	}

	// =========================================================================
	// Schema conversion — lossless types
	// =========================================================================

	public function testTextField(): void {
		[$dto, $report] = $this->convertColumn('fldNAME');
		$this->assertDto($dto, 'text', 'line');
		$this->assertEmpty($report);
	}

	public function testRichTextField(): void {
		[$dto, $report] = $this->convertColumn('fldNOTES');
		$this->assertDto($dto, 'text', 'rich');
		$this->assertEmpty($report);
	}

	public function testUrlField(): void {
		[$dto, $report] = $this->convertColumn('fldURL');
		$this->assertDto($dto, 'text', 'link');
		$this->assertEmpty($report);
	}

	public function testEmailField(): void {
		[$dto, $report] = $this->convertColumn('fldEMAIL');
		$this->assertDto($dto, 'text', 'line');
		$this->assertEmpty($report);
	}

	public function testPhoneField(): void {
		[$dto, $report] = $this->convertColumn('fldPHONE');
		$this->assertDto($dto, 'text', 'line');
		$this->assertEmpty($report);
	}

	public function testNumberField(): void {
		[$dto, $report] = $this->convertColumn('fldBUDGET');
		$this->assertDto($dto, 'number');
		$this->assertSame(2, $dto->numberDecimals);
		$this->assertEmpty($report);
	}

	public function testCurrencyField(): void {
		[$dto, $report] = $this->convertColumn('fldAMOUNT');
		$this->assertDto($dto, 'number');
		$this->assertSame('$', $dto->numberPrefix);
		$this->assertSame(2, $dto->numberDecimals);
		$this->assertEmpty($report);
	}

	public function testPercentField(): void {
		[$dto, $report] = $this->convertColumn('fldPCT');
		$this->assertDto($dto, 'number');
		$this->assertSame('%', $dto->numberSuffix);
		$this->assertEmpty($report);
	}

	public function testRatingField(): void {
		[$dto, $report] = $this->convertColumn('fldPRIO');
		$this->assertDto($dto, 'number', 'stars');
		$this->assertSame(5.0, $dto->numberMax);
		$this->assertEmpty($report);
	}

	public function testCheckboxField(): void {
		[$dto, $report] = $this->convertColumn('fldCOMPLETE');
		$this->assertDto($dto, 'selection', 'check');
		$this->assertEmpty($report);
	}

	public function testDateField(): void {
		[$dto, $report] = $this->convertColumn('fldDUE');
		$this->assertDto($dto, 'datetime', 'date');
		$this->assertEmpty($report);
	}

	public function testDateTimeField(): void {
		[$dto, $report] = $this->convertColumn('fldUPDATED');
		$this->assertDto($dto, 'datetime', 'datetime');
		$this->assertEmpty($report);
	}

	public function testCreatedTimeField(): void {
		[$dto, $report] = $this->convertColumn('fldCREATED');
		$this->assertDto($dto, 'datetime', 'datetime');
		$this->assertEmpty($report);
	}

	public function testLastModifiedTimeField(): void {
		[$dto, $report] = $this->convertColumn('fldMODIFIED');
		$this->assertDto($dto, 'datetime', 'datetime');
		$this->assertEmpty($report);
	}

	public function testSingleSelectField(): void {
		[$dto, $report] = $this->convertColumn('fldSTATUS');
		$this->assertDto($dto, 'selection', 'single');
		$options = json_decode($dto->selectionOptions, true);
		$this->assertCount(3, $options);
		$this->assertSame('Todo', $options[0]['label']);
		$this->assertSame(1, $options[0]['id']);
		$this->assertEmpty($report);
	}

	public function testMultiSelectField(): void {
		[$dto, $report] = $this->convertColumn('fldTAGS');
		$this->assertDto($dto, 'selection', 'multi');
		$options = json_decode($dto->selectionOptions, true);
		$this->assertCount(3, $options);
		$this->assertSame('Design', $options[0]['label']);
		$this->assertEmpty($report);
	}

	// =========================================================================
	// Schema conversion — lossy types (return column + emit report row)
	// =========================================================================

	public function testDurationFieldIsLossy(): void {
		[$dto, $report] = $this->convertColumn('fldDUR');
		$this->assertNotNull($dto);
		$this->assertSame('number', $dto->type);
		$this->assertSame('s', $dto->numberSuffix);
		$this->assertCount(1, $report);
		$this->assertReportRow($report[0], 'Time spent', 'duration', 'field');
	}

	public function testSingleCollaboratorIsLossy(): void {
		[$dto, $report] = $this->convertColumn('fldOWNER');
		$this->assertNotNull($dto);
		$this->assertSame('text', $dto->type);
		$this->assertCount(1, $report);
		$this->assertReportRow($report[0], 'Owner', 'singleCollaborator', 'field');
	}

	public function testMultipleCollaboratorsIsLossy(): void {
		[$dto, $report] = $this->convertColumn('fldTEAM');
		$this->assertNotNull($dto);
		$this->assertCount(1, $report);
		$this->assertReportRow($report[0], 'Team', 'multipleCollaborators', 'field');
	}

	public function testBarcodeFieldIsLossy(): void {
		[$dto, $report] = $this->convertColumn('fldBARCODE');
		$this->assertNotNull($dto);
		$this->assertSame('text', $dto->type);
		$this->assertCount(1, $report);
		$this->assertReportRow($report[0], 'Barcode', 'barcode', 'field');
	}

	// =========================================================================
	// Schema conversion — skip-and-report types (return null + emit report row)
	// =========================================================================

	public function testAutoNumberIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldROWID');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
		$this->assertReportRow($report[0], 'Row #', 'autoNumber', 'field');
	}

	public function testFormulaIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldFORMULA');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
		$this->assertReportRow($report[0], 'Auto total', 'formula', 'field');
		$this->assertStringContainsString('{Budget} + {Amount}', $report[0]['reason']);
	}

	public function testForeignKeyIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldLINKED');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
		$this->assertReportRow($report[0], 'Related tasks', 'foreignKey', 'field');
	}

	public function testLookupIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldLOOKUP');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
	}

	public function testRollupIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldROLLUP');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
	}

	public function testCountIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldCOUNT');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
	}

	public function testButtonIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldBUTTON');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
	}

	public function testAttachmentIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldATTACH');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
	}

	public function testAiTextIsSkipped(): void {
		[$dto, $report] = $this->convertColumn('fldAITEXT');
		$this->assertNull($dto);
		$this->assertCount(1, $report);
	}

	// =========================================================================
	// Value conversion — row 1 (all fields populated)
	// =========================================================================

	public function testRow1TextValue(): void {
		$this->assertConvertedValue('fldNAME', $this->row1['fldNAME'], 'Website redesign');
	}

	public function testRow1NumberValue(): void {
		$this->assertConvertedValue('fldBUDGET', $this->row1['fldBUDGET'], 9500.50);
	}

	public function testRow1CurrencyValue(): void {
		$this->assertConvertedValue('fldAMOUNT', $this->row1['fldAMOUNT'], 1200.0);
	}

	public function testRow1PercentValue(): void {
		// 0.75 → 75.0
		$this->assertConvertedValue('fldPCT', $this->row1['fldPCT'], 75.0);
	}

	public function testRow1RatingValue(): void {
		$this->assertConvertedValue('fldPRIO', $this->row1['fldPRIO'], 4);
	}

	public function testRow1DurationValue(): void {
		$this->assertConvertedValue('fldDUR', $this->row1['fldDUR'], 7200);
	}

	public function testRow1CheckboxTrueValue(): void {
		$this->assertConvertedValue('fldCOMPLETE', $this->row1['fldCOMPLETE'], true);
	}

	public function testRow1DateValue(): void {
		$this->assertConvertedValue('fldDUE', $this->row1['fldDUE'], '2026-06-30');
	}

	public function testRow1DateTimeValue(): void {
		$this->assertConvertedValue('fldCREATED', $this->row1['fldCREATED'], '2026-01-15T10:00:00.000Z');
	}

	public function testRow1SingleSelectValue(): void {
		// 'In Progress' is the second option → id 2
		$this->assertConvertedValue('fldSTATUS', $this->row1['fldSTATUS'], 2);
	}

	public function testRow1MultiSelectValue(): void {
		// 'Design' = id 1, 'Development' = id 2
		$this->assertConvertedValue('fldTAGS', $this->row1['fldTAGS'], [1, 2]);
	}

	public function testRow1CollaboratorExtractsName(): void {
		$this->assertConvertedValue('fldOWNER', $this->row1['fldOWNER'], 'Alice');
	}

	public function testRow1MultipleCollaboratorsJoined(): void {
		$this->assertConvertedValue('fldTEAM', $this->row1['fldTEAM'], 'Alice, Bob');
	}

	public function testRow1BarcodeExtractsText(): void {
		$this->assertConvertedValue('fldBARCODE', $this->row1['fldBARCODE'], '9780201379624');
	}

	public function testRow1FormulaValueIsNull(): void {
		// Formula columns are skipped — converter always returns null
		$this->assertConvertedValue('fldFORMULA', $this->row1['fldFORMULA'], null);
	}

	public function testRow1AttachmentValueIsNull(): void {
		$this->assertConvertedValue('fldATTACH', $this->row1['fldATTACH'], null);
	}

	public function testRow1AutoNumberValueIsNull(): void {
		$this->assertConvertedValue('fldROWID', $this->row1['fldROWID'], null);
	}

	// =========================================================================
	// Value conversion — row 2 (partial / null values)
	// =========================================================================

	public function testRow2NullNumberIsNull(): void {
		$this->assertConvertedValue('fldBUDGET', $this->row2['fldBUDGET'], null);
	}

	public function testRow2ZeroDurationIsZero(): void {
		$this->assertConvertedValue('fldDUR', $this->row2['fldDUR'], 0);
	}

	public function testRow2CheckboxFalseValue(): void {
		$this->assertConvertedValue('fldCOMPLETE', $this->row2['fldCOMPLETE'], false);
	}

	public function testRow2ZeroPercentValue(): void {
		$this->assertConvertedValue('fldPCT', $this->row2['fldPCT'], 0.0);
	}

	public function testRow2MissingFieldIsNull(): void {
		// 'fldNOTES' is absent from row2 — simulate with explicit null
		$this->assertConvertedValue('fldNOTES', null, null);
	}

	// =========================================================================
	// Full pipeline: process all fixture columns, count report rows
	// =========================================================================

	public function testFullPipelineReportRowCount(): void {
		$reportRows = [];
		$losslessCount = 0;
		$lossyCount    = 0;
		$skipCount     = 0;
		$unknownCount  = 0;

		foreach ($this->columns as $col) {
			$converter = $this->registry->get($col['type']);
			if ($converter === null) {
				$unknownCount++;
				continue;
			}

			$before = count($reportRows);
			$dto    = $converter->toTablesColumn($col, $reportRows);
			$after  = count($reportRows);
			$added  = $after - $before;

			if ($dto === null && $added > 0) {
				$skipCount++;
			} elseif ($dto !== null && $added > 0) {
				$lossyCount++;
			} else {
				$losslessCount++;
			}
		}

		// All types in the fixture must be registered — no unknowns.
		$this->assertSame(0, $unknownCount, 'All fixture column types must be registered');

		// Lossless: text, richText, url, email, phone, number, currency, percent,
		//           rating, checkbox, date, dateTime, createdTime, lastModifiedTime,
		//           singleSelect, multiSelect = 16 fields
		$this->assertSame(16, $losslessCount);

		// Lossy (column created + report row): duration, singleCollaborator,
		//           multipleCollaborators, barcode, createdBy, lastModifiedBy = 6 fields
		$this->assertSame(6, $lossyCount);

		// Skip (null column + report row): autoNumber, formula, foreignKey,
		//           lookup, rollup, count, button, multipleAttachments, aiText = 9 fields
		$this->assertSame(9, $skipCount);

		// Total report rows = 6 (lossy) + 9 (skip) = 15
		$this->assertCount(15, $reportRows);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Run toTablesColumn() for a fixture column by field ID.
	 *
	 * @return array{0: ColumnDto|null, 1: array}
	 */
	private function convertColumn(string $fieldId): array {
		$col  = $this->columns[$fieldId];
		$report = [];
		$converter = $this->registry->get($col['type']);
		$this->assertNotNull($converter, "No converter registered for field '{$fieldId}' type '{$col['type']}'");
		$dto = $converter->toTablesColumn($col, $report);
		return [$dto, $report];
	}

	/**
	 * Assert that toTablesValue() for a fixture column produces the expected result.
	 */
	private function assertConvertedValue(string $fieldId, mixed $rawValue, mixed $expected): void {
		$col = $this->columns[$fieldId];
		$converter = $this->registry->get($col['type']);
		$this->assertNotNull($converter);
		$report = [];
		$result = $converter->toTablesValue($rawValue, $col, $report);
		$this->assertEquals($expected, $result, "Value mismatch for field '{$fieldId}'");
	}

	private function assertDto(?ColumnDto $dto, string $type, ?string $subtype = null): void {
		$this->assertNotNull($dto, "Expected a ColumnDto but got null");
		$this->assertSame($type, $dto->type, "Wrong column type");
		if ($subtype !== null) {
			$this->assertSame($subtype, $dto->subtype, "Wrong column subtype");
		}
	}

	/**
	 * @param array<string, string> $row
	 */
	private function assertReportRow(array $row, string $name, string $airtableType, string $objectType): void {
		$this->assertSame($name,         $row['object_name'],   'Wrong object_name in report row');
		$this->assertSame($airtableType, $row['airtable_type'], 'Wrong airtable_type in report row');
		$this->assertSame($objectType,   $row['object_type'],   'Wrong object_type in report row');
		$this->assertNotEmpty($row['reason'], 'Report row reason must not be empty');
	}

	/**
	 * Return one instance of each Phase 0 converter.
	 *
	 * @return list<\OCA\Tables\Service\Airtable\AirtableColumnTypeInterface>
	 */
	private function allConverters(): array {
		return [
			// Text
			new TextConverter(),
			new SingleLineTextConverter(),
			new MultilineTextConverter(),
			new RichTextConverter(),
			new UrlConverter(),
			new EmailConverter(),
			new PhoneConverter(),
			// Numeric
			new NumberConverter(),
			new CurrencyConverter(),
			new PercentConverter(),
			new RatingConverter(),
			new DurationConverter(),
			// Boolean
			new CheckboxConverter(),
			// Date/time
			new DateConverter(),
			new DateTimeConverter(),
			new CreatedTimeConverter(),
			new LastModifiedTimeConverter(),
			// Selection
			new SingleSelectConverter(),
			new MultiSelectConverter(),
			// Collaborator
			new CollaboratorConverter(),
			new MultipleCollaboratorsConverter(),
			new CreatedByConverter(),
			new LastModifiedByConverter(),
			// Misc
			new BarcodeConverter(),
			new AutoNumberConverter(),
			// Skip-and-report
			new FormulaSkipConverter(),
			new ForeignKeySkipConverter(),
			new LookupSkipConverter(),
			new RollupSkipConverter(),
			new CountSkipConverter(),
			new ButtonSkipConverter(),
			new AttachmentSkipConverter(),
			new AiTextSkipConverter(),
			new ExternalSyncSkipConverter(),
		];
	}
}
