<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Model\RowDataInput;
use PHPUnit\Framework\TestCase;

class RowDataInputTest extends TestCase {
	public function testValidatedCellsPreserveNullAndNormalizeIntegerIds(): void {
		$input = RowDataInput::createFromInputArray([
			['columnId' => '0012', 'value' => null],
			['columnId' => '-2', 'value' => 'alice'],
			['columnId' => 13, 'value' => true],
		]);
		$this->assertSame([
			['columnId' => 12, 'value' => null],
			['columnId' => -2, 'value' => 'alice'],
			['columnId' => 13, 'value' => 1],
		], iterator_to_array($input));
	}

	/** @dataProvider malformedCells */
	public function testMalformedCellsAreRejected(array $cells): void {
		$this->expectException(\InvalidArgumentException::class);
		RowDataInput::createFromInputArray($cells);
	}

	public static function malformedCells(): array {
		return [
			[['column_1' => 'value']],
			[[['columnId' => 1]]],
			[[['value' => 'value']]],
			[[['columnId' => 'garbage', 'value' => null]]],
			[[['columnId' => '92233720368547758070', 'value' => null]]],
			[[['columnId' => 1.5, 'value' => null]]],
			[[['columnId' => true, 'value' => null]]],
		];
	}
}
