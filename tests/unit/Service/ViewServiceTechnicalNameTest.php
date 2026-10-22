<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\ViewService;
use PHPUnit\Framework\TestCase;

class ViewServiceTechnicalNameTest extends TestCase {
	public static function invalidNames(): array {
		return [
			'too long' => [str_repeat('a', 201), 'Technical name must not exceed 200 characters.'],
			'empty' => ['', 'Technical name must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.'],
			'upper case' => ['Sales', 'Technical name must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.'],
			'starts with a digit' => ['1sales', 'Technical name must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.'],
			'dash' => ['sales-2', 'Technical name must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.'],
		];
	}

	/**
	 * @dataProvider invalidNames
	 */
	public function testAnInvalidNameIsRefusedWithItsMessage(string $technicalName, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->assertValid($technicalName);
	}

	public function testValidNamesAreAccepted(): void {
		$this->expectNotToPerformAssertions();
		$this->assertValid('sales');
		$this->assertValid('view_12');
		$this->assertValid('a' . str_repeat('b', 199));
	}

	private function assertValid(string $technicalName): void {
		$method = new \ReflectionMethod(ViewService::class, 'assertTechnicalNameValid');
		$method->invoke((new \ReflectionClass(ViewService::class))->newInstanceWithoutConstructor(), $technicalName);
	}
}
