<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Helper;

use OCA\Tables\Helper\IconHelper;
use PHPUnit\Framework\TestCase;

class IconHelperTest extends TestCase {
	public function testAShippedIconIsValid(): void {
		$this->assertTrue(IconHelper::isValid('star'));
		$this->assertTrue(IconHelper::isValid('home'));
	}

	public function testAnUnknownOrMalformedNameIsNotValid(): void {
		foreach (['', 'no-such-icon', '../img/material/star', 'star.svg', 'star ', '📊'] as $name) {
			$this->assertFalse(IconHelper::isValid($name), $name);
		}
	}
}
