<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\ColumnTypes\RelationBusiness;
use OCA\Tables\Service\RelationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RelationBusinessTest extends TestCase {

	private RelationBusiness $business;
	private RelationService&MockObject $relationService;
	private Column&MockObject $column;

	protected function setUp(): void {
		parent::setUp();
		$this->relationService = $this->createMock(RelationService::class);
		$this->business = new RelationBusiness(
			$this->createMock(LoggerInterface::class),
			$this->relationService,
		);
		$this->column = $this->createMock(Column::class);
		$this->column->method('getCustomSettingsArray')->willReturn([]);
		$this->relationService->method('getRelationData')->willReturn([
			13 => ['id' => 13, 'label' => 'Alice'],
			14 => ['id' => 14, 'label' => 'Bob'],
			15 => ['id' => 15, 'label' => 'Carol'],
		]);
	}

	public function testParseValueSingleId(): void {
		$result = json_decode($this->business->parseValue(13, $this->column), true);
		$this->assertSame([13], $result);
	}

	public function testParseValueMultipleIds(): void {
		$result = json_decode($this->business->parseValue([13, 15], $this->column), true);
		$this->assertSame([13, 15], $result);
	}

	public function testParseValueLabels(): void {
		$result = json_decode($this->business->parseValue('Alice, Carol', $this->column), true);
		$this->assertSame([13, 15], $result);
	}

	public function testParseValueEmpty(): void {
		$result = json_decode($this->business->parseValue(null, $this->column), true);
		$this->assertSame([], $result);
	}

	public function testValidateValueRejectsUnknownId(): void {
		$this->expectException(BadRequestError::class);
		$this->business->validateValue(999, $this->column, 'admin', 1, null);
	}

	public function testValidateValueRejectsMultipleWhenNotAllowed(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Relation column does not allow multiple values');
		$this->business->validateValue([13, 14], $this->column, 'admin', 1, null);
	}

	public function testValidateValueAllowsMultipleWhenEnabled(): void {
		$column = $this->createMock(Column::class);
		$column->method('getCustomSettingsArray')->willReturn([Column::RELATION_ALLOW_MULTIPLE => true]);
		$this->business->validateValue([13, 14], $column, 'admin', 1, null);
		$this->addToAssertionCount(1);
	}

	public function testCanBeParsedDisplayValueKeepsPartialMatches(): void {
		$this->assertTrue($this->business->canBeParsedDisplayValue('Alice, Unknown', $this->column));
		$result = json_decode($this->business->parseValue('Alice, Unknown', $this->column), true);
		$this->assertSame([13], $result);
	}

	public function testCanBeParsedDisplayValueRejectsAllInvalid(): void {
		$this->assertFalse($this->business->canBeParsedDisplayValue('Unknown', $this->column));
	}

	public function testCanBeParsedStillRejectsPartialInvalid(): void {
		$this->assertFalse($this->business->canBeParsed('Alice, Unknown', $this->column));
	}
}
