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
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => '[' . $text . ']');
		$this->types = new GridWidgetTypes($l10n);
	}

	public function testEveryTypeDescribesItsSizeSourceAndSettings(): void {
		$all = $this->types->all();

		$this->assertSame(['header', 'text', 'data'], array_keys($all));
		foreach ($all as $name => $type) {
			$this->assertSame($name, $type['type']);
			$this->assertSame($name === 'data', $type['hasSource']);
		}
		$this->assertSame(['heading', 'subheading', 'textAlign', 'backgroundColor', 'textColor'], array_keys($all['header']['settings']));
		$this->assertSame([12, 5], [$all['data']['defaultWidth'], $all['data']['defaultHeight']]);
		$this->assertSame([], $all['data']['settings']);
	}

	public function testTitlesAreTranslated(): void {
		$header = $this->types->get('header');

		$this->assertSame('[Header]', $header['title']);
		$this->assertSame('[Heading]', $header['settings']['heading']['title']);
		$this->assertSame('[Centered]', $header['settings']['textAlign']['options'][1]['label']);
		$this->assertSame('[Description]', $this->types->get('text')['title']);
	}

	public function testUnknownTypeIsRejected(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Unknown widget type "chart"');
		$this->types->get('chart');
	}

	public function testSanitizeFillsDefaultsAndDropsUnknownSettings(): void {
		$this->assertSame(
			['heading' => 'Welcome', 'subheading' => '', 'textAlign' => 'center', 'backgroundColor' => '#fff', 'textColor' => ''],
			$this->types->sanitizeSettings('header', ['heading' => 'Welcome', 'textAlign' => 'center', 'backgroundColor' => '#fff', 'script' => '<b>']),
		);
		$this->assertSame(['text' => ''], $this->types->sanitizeSettings('text', null));
		$this->assertSame([], $this->types->sanitizeSettings('data', ['pageSize' => 10]));
	}

	public static function invalidSettings(): array {
		return [
			'settings not an object' => ['text', 'x', 'settings of a text widget must be an object'],
			'required heading missing' => ['header', ['heading' => '  '], 'header.heading is required'],
			'text not a string' => ['text', ['text' => 3], 'text.text must be a string'],
			'color not hex' => ['header', ['heading' => 'A', 'textColor' => 'red'], 'header.textColor must be a hex color'],
			'color of four digits' => ['header', ['heading' => 'A', 'textColor' => '#abcd'], 'header.textColor must be a hex color'],
			'color of five digits' => ['header', ['heading' => 'A', 'textColor' => '#abcde'], 'header.textColor must be a hex color'],
			'settings too large' => ['text', ['text' => str_repeat('a', 60000)], 'take more than 60000 bytes'],
			'text not utf-8' => ['text', ['text' => "\xff"], 'must be valid UTF-8 text'],
			'color with a trailing newline' => ['header', ['heading' => 'A', 'textColor' => "#abc\n"], 'header.textColor must be a hex color'],
			'alignment unknown' => ['header', ['heading' => 'A', 'textAlign' => 'justify'], 'header.textAlign must be one of'],
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

	public function testATitleIsTrimmedAndAnEmptyOneIsNone(): void {
		$this->assertSame('Notes', $this->types->validateTitle(0, '  Notes '));
		$this->assertNull($this->types->validateTitle(0, '  '));
		$this->assertNull($this->types->validateTitle(0, null));
		$this->assertSame(str_repeat('a', 200), $this->types->validateTitle(0, str_repeat('a', 200)));
	}

	public function testATitleThatIsTooLongOrNoTextIsRefused(): void {
		foreach ([str_repeat('a', 201), 5, ['a']] as $title) {
			try {
				$this->types->validateTitle(2, $title);
				$this->fail('Accepted ' . json_encode($title));
			} catch (BadRequestError $e) {
				$this->assertStringContainsString('The title of widget 2', $e->getMessage());
			}
		}
	}

	public function testShortAndLongHexColorsAreAccepted(): void {
		$settings = $this->types->sanitizeSettings('header', ['heading' => 'A', 'textColor' => '#abc', 'backgroundColor' => '#A0b1C2']);
		$this->assertSame(['#abc', '#A0b1C2'], [$settings['textColor'], $settings['backgroundColor']]);
	}

	public function testADescriptionIsTrimmedAndAnEmptyOneIsNone(): void {
		$this->assertSame('Open tasks', $this->types->validateDescription(0, ' Open tasks '));
		$this->assertNull($this->types->validateDescription(0, ' '));
		$this->assertNull($this->types->validateDescription(0, null));
		$this->assertSame(str_repeat('a', 2000), $this->types->validateDescription(0, str_repeat('a', 2000)));
	}

	public function testADescriptionThatIsTooLongOrNoTextIsRefused(): void {
		foreach ([str_repeat('a', 2001), 5, ['a']] as $description) {
			try {
				$this->types->validateDescription(3, $description);
				$this->fail('Accepted ' . json_encode($description));
			} catch (BadRequestError $e) {
				$this->assertStringContainsString('The description of widget 3', $e->getMessage());
			}
		}
	}

	public function testSettingsUpToTheLimitAreKept(): void {
		// {"text":"…"} adds 11 bytes around the text
		$text = str_repeat('a', GridWidgetTypes::SETTINGS_MAX_BYTES - 11);
		$this->assertSame(['text' => $text], $this->types->sanitizeSettings('text', ['text' => $text]));
	}
}
