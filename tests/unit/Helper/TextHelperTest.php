<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Helper;

use OCA\Tables\Errors\BadRequestError;
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

	public static function titlesAndDescriptions(): array {
		return [
			'title trimmed' => ['title', '  Notes ', 'Notes'],
			'title of the maximum length' => ['title', str_repeat('a', 200), str_repeat('a', 200)],
			'empty title' => ['title', '  ', null],
			'title of invisible spaces' => ['title', "\u{00A0}\u{3000}\u{200B}", null],
			'no title' => ['title', null, null],
			'title too long' => ['title', str_repeat('a', 201), 'The title of widget 2'],
			'title not text' => ['title', 5, 'The title of widget 2'],
			'description trimmed' => ['description', ' Open tasks ', 'Open tasks'],
			'description of the maximum length' => ['description', str_repeat('a', 2000), str_repeat('a', 2000)],
			'empty description' => ['description', ' ', null],
			'description too long' => ['description', str_repeat('a', 2001), 'The description of widget 2'],
			'description not text' => ['description', ['a'], 'The description of widget 2'],
		];
	}

	/**
	 * @dataProvider titlesAndDescriptions
	 * @param 'title'|'description' $method
	 * @param ?string $expected the text kept, or the start of the message it is refused with
	 */
	public function testATitleOrDescriptionIsTrimmedOrRefused(string $method, mixed $text, ?string $expected): void {
		if ($expected !== null && str_starts_with($expected, 'The ')) {
			$this->expectException(BadRequestError::class);
			$this->expectExceptionMessage($expected);
		}
		$this->assertSame($expected, TextHelper::$method($text, 'widget 2'));
	}
}
