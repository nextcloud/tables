<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Model\RowIdentifier;
use PHPUnit\Framework\TestCase;

class RowIdentifierTest extends TestCase {
	/** @dataProvider validIdentifiers */
	public function testValidIdentifier(string $input, int|string $expected): void {
		$identifier = RowIdentifier::fromString($input);
		$this->assertSame($expected, $identifier->value);
		$this->assertSame(is_string($expected), $identifier->isUuid());
	}

	public static function validIdentifiers(): array {
		return [
			['1', 1], ['00123', 123], ['2147483647', 2147483647],
			['019F90C5-8576-7F0B-9B14-9BF49B2826CF', '019f90c5-8576-7f0b-9b14-9bf49b2826cf'],
		];
	}

	/** @dataProvider invalidIdentifiers */
	public function testInvalidIdentifier(string $input): void {
		$this->expectException(\InvalidArgumentException::class);
		RowIdentifier::fromString($input);
	}

	public static function invalidIdentifiers(): array {
		return array_map(static fn (string $input): array => [$input], [
			'', '0', '000', '-1', '+1', ' 1', '1 ', '1.0', '1e3', "1\n", '１２',
			'2147483648', (string)PHP_INT_MAX, (string)PHP_INT_MAX . '0', '019f90c5-8576-4f0b-9b14-9bf49b2826cf',
			'019f90c5-8576-7f0b-0b14-9bf49b2826cf', '019f90c585767f0b9b149bf49b2826cf',
		]);
	}

	public function testIntegerWithinColumnRangeBecomesIdentifier(): void {
		$this->assertSame(13, RowIdentifier::tryFromInt(13)?->value);
		$this->assertSame(2147483647, RowIdentifier::tryFromInt(2147483647)?->value);
	}

	/** @dataProvider integersOutsideColumnRange */
	public function testIntegerOutsideColumnRangeHasNoIdentifier(int $rowId): void {
		$this->assertNull(RowIdentifier::tryFromInt($rowId));
	}

	public static function integersOutsideColumnRange(): array {
		return [[0], [-1], [2147483648], [PHP_INT_MAX]];
	}
}
