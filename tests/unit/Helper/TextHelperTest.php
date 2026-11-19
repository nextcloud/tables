<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Helper;

use OCA\Tables\Helper\TextHelper;
use PHPUnit\Framework\TestCase;

class TextHelperTest extends TestCase {
	public static function texts(): array {
		return [
			'kept' => ['Overview', 'Overview'],
			'trimmed' => ["  Overview\n", 'Overview'],
			'no text' => [null, null],
			'empty' => ['', null],
			'no-break spaces only' => ["\u{00A0}\u{00A0}", null],
			'ideographic space only' => ["\u{3000}", null],
			'zero width spaces only' => ["\u{200B}\u{FEFF}", null],
			'no-break space around text' => ["\u{00A0}Sales\u{200B}", 'Sales'],
			'non-Latin text counts characters' => [str_repeat('я', 10), str_repeat('я', 10)],
		];
	}

	/**
	 * @dataProvider texts
	 */
	public function testAnOptionalTextIsTrimmedOfEveryBlank(mixed $text, ?string $expected): void {
		$this->assertSame($expected, TextHelper::optional($text, 10));
	}

	public static function refusedTexts(): array {
		return [
			'too long' => [str_repeat('a', 11)],
			'not a text' => [5],
		];
	}

	/**
	 * @dataProvider refusedTexts
	 */
	public function testATextThatIsNoTextOrTooLongIsRefused(mixed $text): void {
		$this->expectException(\InvalidArgumentException::class);
		TextHelper::optional($text, 10);
	}
}
