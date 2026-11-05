<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Helper;

use OCA\Tables\Helper\CircleHelper;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Helper\UserHelper;
use PHPUnit\Framework\TestCase;

final class ColumnsHelperTest extends TestCase {
	private ColumnsHelper $helper;

	protected function setUp(): void {
		parent::setUp();

		$userHelper = $this->createMock(UserHelper::class);
		$userHelper->method('getUserDisplayName')->willReturnCallback(fn (string $userId): string => 'Name of ' . $userId);
		$this->helper = new ColumnsHelper($userHelper, $this->createMock(CircleHelper::class));
	}

	public static function placeholderProvider(): array {
		return [
			'@me' => ['@me', true],
			'@my-name' => ['@my-name', true],
			'unknown placeholder' => ['@my-email', true],
			'plain value' => ['done', false],
			'selection' => ['@selection-id-12', false],
			'relation' => ['@relation-id-3', false],
			'checked' => ['@checked', false],
			'unchecked' => ['@unchecked', false],
			'stars' => ['@stars-3', false],
			'today' => ['@datetime-date-today', false, true],
			'start of year' => ['@datetime-date-start-of-year', false, true],
			'start of month' => ['@datetime-date-start-of-month', false, true],
			'start of week' => ['@datetime-date-start-of-week', false, true],
			'time now' => ['@datetime-time-now', false, true],
			'now' => ['@datetime-now', false, true],
			'exact date' => ['@datetime-exact-date:2026-01-01', false],
			'days ahead' => ['@datetime-days-ahead:3', false, true],
			'days ago' => ['@datetime-days-ago:3', false, true],
		];
	}

	/**
	 * @dataProvider placeholderProvider
	 */
	public function testUserDependenceMatchesResolution(string $value, bool $userDependent, bool $clockBased = false): void {
		$this->assertSame($userDependent, $this->helper->isUserDependentPlaceholder($value));

		// Clock based values may change between two calls, and unknown placeholders resolve to themselves
		if ($clockBased || $value === '@my-email') {
			return;
		}
		$alice = $this->helper->resolveSearchValue($value, 'alice');
		$bob = $this->helper->resolveSearchValue($value, 'bob');
		$this->assertSame($userDependent, $alice !== $bob);
	}
}
