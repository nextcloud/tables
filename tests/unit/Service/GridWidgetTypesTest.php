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
			['heading' => 'Welcome', 'subheading' => '', 'textAlign' => 'center', 'color' => 'warning'],
			$this->types->sanitizeSettings('header', ['heading' => 'Welcome', 'textAlign' => 'center', 'color' => 'warning', 'script' => '<b>']),
		);
		$this->assertSame(['heading' => 'Welcome', 'subheading' => '', 'textAlign' => 'start', 'color' => 'primary'], $this->types->sanitizeSettings('header', ['heading' => 'Welcome']));
		$this->assertSame([], $this->types->sanitizeSettings('tables', null));
		$this->assertSame(['text' => ''], $this->types->sanitizeSettings('text', null));
		$this->assertSame([], $this->types->sanitizeSettings('data', ['pageSize' => 10]));
	}

	public function testTheTextWidgetIsCalledTextAndExplainsParagraphs(): void {
		$text = $this->types->get('text');
		$this->assertSame('Text', $text['title']);
		$this->assertSame('Leave an empty line between paragraphs', $text['settings']['text']['hint']);
		$this->assertArrayNotHasKey('hint', $this->types->get('header')['settings']['heading']);
	}

	public function testSettingsUpToTheLimitAreKept(): void {
		// {"text":"…"} adds 11 bytes around the text
		$text = str_repeat('a', GridWidgetTypes::SETTINGS_MAX_BYTES - 11);
		$this->assertSame(['text' => $text], $this->types->sanitizeSettings('text', ['text' => $text]));
	}

	public function testTheSettingsLimitCountsNonLatinTextAsWritten(): void {
		$text = str_repeat('я', 10000) . '/';
		$this->assertSame(['text' => $text], $this->types->sanitizeSettings('text', ['text' => $text]));
	}

	public static function invalidSettings(): array {
		return [
			'unknown type' => ['chart', [], 'Unknown widget type "chart"'],
			'settings not an object' => ['text', 'x', 'settings of a text widget must be an object'],
			'required heading missing' => ['header', ['heading' => '  '], 'header.heading is required'],
			'required heading of a no-break space' => ['header', ['heading' => "\u{00A0}\u{200B}"], 'header.heading is required'],
			'text not a string' => ['text', ['text' => 3], 'text.text must be a string'],
			'a free color' => ['header', ['heading' => 'A', 'color' => '#123456'], 'header.color must be one of'],
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

	public function testAWidgetIsReadWithEveryField(): void {
		$widget = $this->types->readWidget(0, [
			'id' => 31, 'type' => 'data', 'title' => ' Tasks ', 'icon' => 'star', 'description' => 'Open',
			'gridX' => 0, 'gridY' => 2, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => ['unknown' => 1], 'source' => ['nodeRelId' => 7],
		]);

		$this->assertSame([31, 'data', 'Tasks', 'star', 'Open', 0, 2, 12, 5, [], 7], [
			$widget->id, $widget->type, $widget->title, $widget->icon, $widget->description,
			$widget->gridX, $widget->gridY, $widget->gridWidth, $widget->gridHeight, $widget->settings, $widget->nodeRelId,
		]);
	}

	public static function unreadableWidgets(): array {
		$widget = ['type' => 'text', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2];
		return [
			'not an object' => ['text', 'Widget 1 must be an object'],
			'no type' => [['gridX' => 0], 'Widget 1 needs a type'],
			'unknown type' => [['type' => 'chart'] + $widget, 'Unknown widget type'],
			'id not a number' => [['id' => '3'] + $widget, 'The id of widget 1 must be a number'],
			'unknown icon' => [['icon' => 'no-such-icon'] + $widget, 'The icon of widget 1'],
			'source on a type without one' => [['source' => ['nodeRelId' => 7]] + $widget, 'Widget 1 is a text widget'],
			'source without a number' => [['type' => 'data', 'source' => ['nodeRelId' => '7']] + $widget, 'The source of widget 1'],
		];
	}

	/**
	 * @dataProvider unreadableWidgets
	 */
	public function testAWidgetThatBreaksARuleIsRefused(mixed $widget, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->types->readWidget(1, $widget);
	}
}
