<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Validation;

use OCA\Tables\Validation\TechnicalNameValidator;
use PHPUnit\Framework\TestCase;

class TechnicalNameValidatorTest extends TestCase {
	public function testIsValid(): void {
		$this->assertTrue(TechnicalNameValidator::isValid('open_cases_2'));
		$this->assertTrue(TechnicalNameValidator::isValid(str_repeat('a', 200)));
		foreach (['', 'Sales', '2026', '_sales', 'open-cases', 'café', 'has space', "sales\n", "sales\r\n", str_repeat('a', 201)] as $name) {
			$this->assertFalse(TechnicalNameValidator::isValid($name), $name);
		}
	}

	public function testIsTooLong(): void {
		$this->assertFalse(TechnicalNameValidator::isTooLong(str_repeat('a', 200)));
		$this->assertTrue(TechnicalNameValidator::isTooLong(str_repeat('a', 201)));
	}

	public function testHasValidCharactersIgnoresTheLength(): void {
		$this->assertTrue(TechnicalNameValidator::hasValidCharacters(str_repeat('a', 201)));
		$this->assertFalse(TechnicalNameValidator::hasValidCharacters('1sales'));
		$this->assertFalse(TechnicalNameValidator::hasValidCharacters("sales\n"));
	}
}
