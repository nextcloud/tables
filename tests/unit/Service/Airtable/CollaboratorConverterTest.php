<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\Airtable;

use OCA\Tables\Service\Airtable\ColumnTypes\CollaboratorConverter;
use OCA\Tables\Service\Airtable\ColumnTypes\MultipleCollaboratorsConverter;
use PHPUnit\Framework\TestCase;

class CollaboratorConverterTest extends TestCase {

	// =========================================================================
	// CollaboratorConverter (singleCollaborator)
	// =========================================================================

	public function testSingleCollaboratorGetAirtableType(): void {
		$this->assertSame('singleCollaborator', (new CollaboratorConverter())->getAirtableType());
	}

	public function testSingleCollaboratorToTablesColumnEmitsReportRow(): void {
		$col = ['name' => 'Owner'];
		$report = [];

		$dto = (new CollaboratorConverter())->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('text', $dto->type);
		$this->assertSame('line', $dto->subtype);
		$this->assertCount(1, $report);
		$this->assertSame('Owner', $report[0]['object_name']);
		$this->assertSame('singleCollaborator', $report[0]['airtable_type']);
		$this->assertSame('field', $report[0]['object_type']);
	}

	public function testSingleCollaboratorToTablesValueExtractsName(): void {
		$value = ['id' => 'usrXXX', 'email' => 'alice@example.com', 'name' => 'Alice'];
		$report = [];

		$result = (new CollaboratorConverter())->toTablesValue($value, [], $report);

		$this->assertSame('Alice', $result);
	}

	public function testSingleCollaboratorToTablesValueFallsBackToEmail(): void {
		$value = ['id' => 'usrXXX', 'email' => 'bob@example.com'];
		$report = [];

		$result = (new CollaboratorConverter())->toTablesValue($value, [], $report);

		$this->assertSame('bob@example.com', $result);
	}

	public function testSingleCollaboratorToTablesValueNullReturnsNull(): void {
		$report = [];
		$this->assertNull((new CollaboratorConverter())->toTablesValue(null, [], $report));
	}

	public function testSingleCollaboratorToTablesValueStringPassthrough(): void {
		$report = [];
		$result = (new CollaboratorConverter())->toTablesValue('Carol', [], $report);
		$this->assertSame('Carol', $result);
	}

	// =========================================================================
	// MultipleCollaboratorsConverter
	// =========================================================================

	public function testMultipleCollaboratorsGetAirtableType(): void {
		$this->assertSame('multipleCollaborators', (new MultipleCollaboratorsConverter())->getAirtableType());
	}

	public function testMultipleCollaboratorsToTablesColumnEmitsReportRow(): void {
		$col = ['name' => 'Team'];
		$report = [];

		$dto = (new MultipleCollaboratorsConverter())->toTablesColumn($col, $report);

		$this->assertNotNull($dto);
		$this->assertSame('text', $dto->type);
		$this->assertCount(1, $report);
		$this->assertSame('Team', $report[0]['object_name']);
		$this->assertSame('multipleCollaborators', $report[0]['airtable_type']);
	}

	public function testMultipleCollaboratorsToTablesValueJoinsNames(): void {
		$value = [
			['id' => 'usr1', 'name' => 'Alice'],
			['id' => 'usr2', 'name' => 'Bob'],
		];
		$report = [];

		$result = (new MultipleCollaboratorsConverter())->toTablesValue($value, [], $report);

		$this->assertSame('Alice, Bob', $result);
	}

	public function testMultipleCollaboratorsToTablesValueSkipsEmptyNames(): void {
		$value = [
			['id' => 'usr1', 'name' => 'Alice'],
			['id' => 'usr2'],
		];
		$report = [];

		$result = (new MultipleCollaboratorsConverter())->toTablesValue($value, [], $report);

		// 'usr2' has no name or email — empty string filtered out
		$this->assertSame('Alice', $result);
	}

	public function testMultipleCollaboratorsToTablesValueNullReturnsNull(): void {
		$report = [];
		$this->assertNull((new MultipleCollaboratorsConverter())->toTablesValue(null, [], $report));
	}

	public function testMultipleCollaboratorsToTablesValueEmptyArrayReturnsNull(): void {
		$report = [];
		$this->assertNull((new MultipleCollaboratorsConverter())->toTablesValue([], [], $report));
	}

	public function testMultipleCollaboratorsToTablesValueStringPassthrough(): void {
		$report = [];
		$result = (new MultipleCollaboratorsConverter())->toTablesValue('Dave', [], $report);
		$this->assertSame('Dave', $result);
	}
}
