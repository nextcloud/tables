<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Db;

use OCA\Tables\Db\Context;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase {
	public function testAContextWithoutALoadedMenuLeavesTheMenuOut(): void {
		$this->assertArrayNotHasKey('menuItems', (new Context())->jsonSerialize());
	}

	public function testALoadedMenuIsCarriedEvenWhenEmpty(): void {
		$context = new Context();
		$context->setMenuItems([]);
		$this->assertSame([], $context->jsonSerialize()['menuItems']);

		$context->setMenuItems([3 => ['id' => 3]]);
		$this->assertSame([['id' => 3]], $context->jsonSerialize()['menuItems']);
	}
}
