<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Validation;

use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Validation\ColumnDtoValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Test\TestCase;

class ColumnDtoValidatorTest extends TestCase {
	private ColumnDtoValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new ColumnDtoValidator();
	}

	private function dto(?string $technicalName, ?int $textMaxLength = null, ?float $numberMin = null, ?float $numberMax = null): ColumnDto {
		return new ColumnDto(
			technicalName: $technicalName,
			textMaxLength: $textMaxLength,
			numberMin: $numberMin,
			numberMax: $numberMax,
		);
	}

	public function testNullTechnicalNameIsAccepted(): void {
		$this->expectNotToPerformAssertions();
		$this->validator->validate($this->dto(null));
	}

	public function testEmptyTechnicalNameIsRejected(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessageMatches('/must not be empty/i');
		$this->validator->validate($this->dto(''));
	}

	public function testTechnicalNameExactly200CharsIsAccepted(): void {
		$this->expectNotToPerformAssertions();
		$this->validator->validate($this->dto('tes' . str_repeat('t', 196)));
	}

	public function testTechnicalNameOver200CharsIsRejected(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessageMatches('/maximum.*200/i');
		$this->validator->validate($this->dto('test' . str_repeat('t', 197)));
	}

	/**
	 * @dataProvider reservedNameProvider
	 */
	#[DataProvider('reservedNameProvider')]
	public function testReservedTechnicalNamesAreRejected(string $name): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessageMatches('/reserved/i');
		$this->validator->validate($this->dto($name));
	}

	public static function reservedNameProvider(): array {
		return [
			['id'],
			['created_by'],
			['created_at'],
			['last_edit_by'],
			['last_edit_at'],
			['data'],
		];
	}

	/**
	 * @dataProvider invalidFormatProvider
	 */
	#[DataProvider('invalidFormatProvider')]
	public function testInvalidFormatIsRejected(string $name): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessageMatches('/start with a letter|lowercase|underscores/i');
		$this->validator->validate($this->dto($name));
	}

	public static function invalidFormatProvider(): array {
		return [
			['1starts_with_digit'],
			['_starts_with_underscore'],
			['Has_Uppercase'],
			['has space'],
			['has-dash'],
			['has.dot'],
		];
	}

	/**
	 * @dataProvider validFormatProvider
	 */
	#[DataProvider('validFormatProvider')]
	public function testValidFormatIsAccepted(string $name): void {
		$this->expectNotToPerformAssertions();
		$this->validator->validate($this->dto($name));
	}

	public static function validFormatProvider(): array {
		return [
			['customer_name'],
			['field1'],
			['abc'],
			['a123_xyz'],
			['column_42'],
		];
	}

	public function testNegativeTextMaxLengthIsRejected(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessageMatches('/maximum text length/i');
		$this->validator->validate($this->dto(null, textMaxLength: -1));
	}

	public function testTextMaxLengthZeroIsAccepted(): void {
		$this->expectNotToPerformAssertions();
		$this->validator->validate($this->dto(null, textMaxLength: 0));
	}

	public function testNumberMinGreaterThanMaxIsRejected(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessageMatches('/minimum number must be less/i');
		$this->validator->validate($this->dto(null, numberMin: 10.0, numberMax: 5.0));
	}

	public function testValidNumberRangeIsAccepted(): void {
		$this->expectNotToPerformAssertions();
		$this->validator->validate($this->dto(null, numberMin: 0.0, numberMax: 100.0));
	}
}
