<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\GridWidgetTypes;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class GridWidgetTypesTest extends TestCase {
	private GridWidgetTypes $types;

	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$this->types = new GridWidgetTypes($l10n);
	}

	public function testSanitizeFillsDefaultsAndDropsUnknownSettings(): void {
		$this->assertSame(
			['heading' => 'Welcome', 'subheading' => '', 'textAlign' => 'center', 'backgroundColor' => '#fff', 'textColor' => '#A0b1C2'],
			$this->types->sanitizeSettings('header', ['heading' => 'Welcome', 'textAlign' => 'center', 'backgroundColor' => '#fff', 'textColor' => '#A0b1C2', 'script' => '<b>']),
		);
		$this->assertSame(['heading' => 'Welcome', 'subheading' => '', 'textAlign' => 'start', 'backgroundColor' => '', 'textColor' => ''], $this->types->sanitizeSettings('header', ['heading' => 'Welcome']));
		$this->assertSame(['text' => ''], $this->types->sanitizeSettings('text', null));
		$this->assertSame([], $this->types->sanitizeSettings('data', ['pageSize' => 10]));
	}

	public function testSettingsUpToTheLimitAreKept(): void {
		// {"text":"…"} adds 11 bytes around the text
		$text = str_repeat('a', GridWidgetTypes::SETTINGS_MAX_BYTES - 11);
		$this->assertSame(['text' => $text], $this->types->sanitizeSettings('text', ['text' => $text]));
	}

	public static function invalidSettings(): array {
		return [
			'unknown type' => ['chart', [], 'Unknown widget type "chart"'],
			'settings not an object' => ['text', 'x', 'settings of a text widget must be an object'],
			'required heading missing' => ['header', ['heading' => '  '], 'header.heading is required'],
			'text not a string' => ['text', ['text' => 3], 'text.text must be a string'],
			'color not hex' => ['header', ['heading' => 'A', 'textColor' => 'red'], 'header.textColor must be a hex color'],
			'color of four digits' => ['header', ['heading' => 'A', 'textColor' => '#abcd'], 'header.textColor must be a hex color'],
			'color with a trailing newline' => ['header', ['heading' => 'A', 'textColor' => "#abc\n"], 'header.textColor must be a hex color'],
			'alignment unknown' => ['header', ['heading' => 'A', 'textAlign' => 'left'], 'header.textAlign must be one of'],
			'settings too large' => ['text', ['text' => str_repeat('a', 60000)], 'take more than 60000 bytes'],
			'text not utf-8' => ['text', ['text' => "\xff"], 'must be valid UTF-8 text'],
		];
	}

	/**
	 * @dataProvider invalidSettings
	 */
	public function testSanitizeRejectsInvalidSettings(string $type, mixed $settings, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->types->sanitizeSettings($type, $settings);
	}

	public function testAPositionOnTheGridIsKept(): void {
		$this->assertSame(
			['gridX' => 6, 'gridY' => 3, 'gridWidth' => 6, 'gridHeight' => 100],
			$this->types->validatePosition(0, ['gridX' => 6, 'gridY' => 3, 'gridWidth' => 6, 'gridHeight' => 100, 'other' => 1]),
		);
	}

	public static function invalidPositions(): array {
		$at = ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2];
		return [
			'missing' => [['gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2], 'needs gridX'],
			'not a whole number' => [['gridX' => '1'] + $at, 'needs gridX'],
			'below the grid' => [['gridY' => -1] + $at, 'needs gridY'],
			'too far down' => [['gridY' => 10001] + $at, 'needs gridY'],
			'no width' => [['gridWidth' => 0] + $at, 'needs gridWidth'],
			'too high' => [['gridHeight' => 101] + $at, 'needs gridHeight'],
			'past the last column' => [['gridX' => 7] + $at, 'does not fit in 12 columns'],
		];
	}

	/**
	 * @dataProvider invalidPositions
	 */
	public function testAPositionOffTheGridIsRefused(array $widget, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Widget 3 ' . $message);
		$this->types->validatePosition(3, $widget);
	}

	public static function texts(): array {
		return [
			'title trimmed' => ['validateTitle', '  Notes ', 'Notes'],
			'title of the maximum length' => ['validateTitle', str_repeat('a', 200), str_repeat('a', 200)],
			'empty title' => ['validateTitle', '  ', null],
			'no title' => ['validateTitle', null, null],
			'title too long' => ['validateTitle', str_repeat('a', 201), 'The title of widget 2'],
			'title not text' => ['validateTitle', 5, 'The title of widget 2'],
			'description trimmed' => ['validateDescription', ' Open tasks ', 'Open tasks'],
			'description of the maximum length' => ['validateDescription', str_repeat('a', 2000), str_repeat('a', 2000)],
			'empty description' => ['validateDescription', ' ', null],
			'description too long' => ['validateDescription', str_repeat('a', 2001), 'The description of widget 2'],
			'description not text' => ['validateDescription', ['a'], 'The description of widget 2'],
		];
	}

	/**
	 * @dataProvider texts
	 * @param 'validateTitle'|'validateDescription' $method
	 * @param ?string $expected the text kept, or the start of the message it is refused with
	 */
	public function testATitleOrDescriptionIsTrimmedOrRefused(string $method, mixed $text, ?string $expected): void {
		if ($expected !== null && str_starts_with($expected, 'The ')) {
			$this->expectException(BadRequestError::class);
			$this->expectExceptionMessage($expected);
		}
		$this->assertSame($expected, $this->types->$method(2, $text));
	}
}
