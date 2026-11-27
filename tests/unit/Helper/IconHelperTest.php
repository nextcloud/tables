<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Helper;

use OCA\Tables\Errors\BadRequestError;
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

	public function testAnOptionalIconIsTrimmedAndAnEmptyOneIsNone(): void {
		$this->assertSame('star', IconHelper::optional(' star ', 'a page'));
		$this->assertNull(IconHelper::optional(' ', 'a page'));
		$this->assertNull(IconHelper::optional(null, 'a page'));
	}

	public static function refusedIcons(): array {
		return [
			'unknown' => ['no-such-icon'],
			'not a text' => [5],
		];
	}

	/**
	 * @dataProvider refusedIcons
	 */
	public function testAnOptionalIconThatIsNoNamedIconIsRefused(mixed $icon): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('The icon of a menu item must be the name of an icon.');
		IconHelper::optional($icon, 'a menu item');
	}
}
