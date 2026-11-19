<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Model;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Model\MenuOrderEntry;
use PHPUnit\Framework\TestCase;

class MenuOrderEntryTest extends TestCase {
	public function testAnOrderIsReadInDisplayOrder(): void {
		$this->assertEquals(
			[new MenuOrderEntry(4, null), new MenuOrderEntry(5, 4), new MenuOrderEntry(6, null)],
			MenuOrderEntry::listFromArray([['id' => 4, 'parentId' => null], ['id' => 5, 'parentId' => 4], ['id' => 6]]),
		);
	}

	public static function malformedOrders(): array {
		return [
			'an object instead of a list' => [['first' => ['id' => 4, 'parentId' => null]], 'is a list'],
			'a list with gaps' => [[1 => ['id' => 4, 'parentId' => null]], 'is a list'],
			'an entry that is no object' => [[4], 'Entry 0'],
			'an id as text' => [[['id' => '4', 'parentId' => null]], 'Entry 0'],
			'no id' => [[['parentId' => null]], 'Entry 0'],
			'a parent id as text' => [[['id' => 4, 'parentId' => null], ['id' => 5, 'parentId' => '4']], 'Entry 1'],
			'an extra key' => [[['id' => 4, 'parentId' => null, 'order' => 3]], 'Entry 0'],
		];
	}

	/**
	 * @dataProvider malformedOrders
	 */
	public function testAMalformedOrderIsRefused(array $order, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		MenuOrderEntry::listFromArray($order);
	}
}
