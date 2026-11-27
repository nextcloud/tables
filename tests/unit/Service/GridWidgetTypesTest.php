<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Model\WidgetInput;
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

	public static function texts(): array {
		return [
			'title trimmed' => ['validateTitle', '  Notes ', 'Notes'],
			'title of the maximum length' => ['validateTitle', str_repeat('a', 200), str_repeat('a', 200)],
			'empty title' => ['validateTitle', '  ', null],
			'title of invisible spaces' => ['validateTitle', "\u{00A0}\u{3000}\u{200B}", null],
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

	public function testTabsAreKeptWithTheirKeysAndATabWithoutAKeyGetsOne(): void {
		$tabs = $this->types->sanitizeSettings('tabs', ['tabs' => [
			['key' => 'open_tasks-1', 'title' => ' Open '],
			['title' => 'Done'],
			['key' => '', 'title' => 'Later'],
		]])['tabs'];

		$this->assertSame(['key' => 'open_tasks-1', 'title' => 'Open'], $tabs[0]);
		$this->assertSame('Done', $tabs[1]['title']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $tabs[1]['key']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $tabs[2]['key']);
		$this->assertNotSame($tabs[1]['key'], $tabs[2]['key']);
	}

	public function testTheMaximumNumberOfTabsIsKept(): void {
		$tabs = array_map(fn (int $i) => ['key' => 't' . $i, 'title' => 'Tab ' . $i], range(1, GridWidgetTypes::MAX_TABS));
		$this->assertSame($tabs, $this->types->sanitizeSettings('tabs', ['tabs' => $tabs])['tabs']);
	}

	public function testATabKeyOfTheMaximumLengthIsKept(): void {
		$key = str_repeat('k', GridWidgetTypes::TAB_KEY_MAX_LENGTH);
		$this->assertSame([['key' => $key, 'title' => 'A']], $this->types->sanitizeSettings('tabs', ['tabs' => [['key' => $key, 'title' => 'A']]])['tabs']);
	}

	public static function invalidTabs(): array {
		$tab = ['key' => 'a', 'title' => 'A'];
		return [
			'not a list' => [['first' => $tab], 'tabs.tabs must be a list of tabs'],
			'not an array' => ['A', 'tabs.tabs must be a list of tabs'],
			'missing, so empty' => [null, 'tabs.tabs needs at least one tab'],
			'empty' => [[], 'tabs.tabs needs at least one tab'],
			'too many' => [array_map(fn (int $i) => ['key' => 't' . $i, 'title' => 'T'], range(0, GridWidgetTypes::MAX_TABS)), 'holds at most 20 tabs'],
			'tab not an object' => [['A'], 'Tab 0 of widget setting tabs.tabs needs a title'],
			'no title' => [[['key' => 'a']], 'Tab 0 of widget setting tabs.tabs needs a title'],
			'title not text' => [[['key' => 'a', 'title' => 5]], 'Tab 0 of widget setting tabs.tabs needs a title'],
			'blank title' => [[$tab, ['key' => 'b', 'title' => '  ']], 'Tab 1 of widget setting tabs.tabs needs a title'],
			'title of a no-break and a zero width space' => [[['key' => 'a', 'title' => "\u{00A0}\u{200B}"]], 'Tab 0 of widget setting tabs.tabs needs a title'],
			'title too long' => [[['key' => 'a', 'title' => str_repeat('a', 201)]], 'needs a title of at most 200 characters'],
			'key not text' => [[['key' => 5, 'title' => 'A']], 'Tab 0 of widget setting tabs.tabs needs a key'],
			'key with a space' => [[['key' => 'a b', 'title' => 'A']], 'Tab 0 of widget setting tabs.tabs needs a key'],
			'key with markup' => [[['key' => '<b>', 'title' => 'A']], 'Tab 0 of widget setting tabs.tabs needs a key'],
			'key too long' => [[['key' => str_repeat('k', 33), 'title' => 'A']], 'needs a key of at most 32 letters'],
			'duplicate key' => [[$tab, ['key' => 'a', 'title' => 'B']], 'Tab 1 of widget setting tabs.tabs has the key of another tab'],
		];
	}

	/**
	 * @dataProvider invalidTabs
	 */
	public function testTabsThatBreakARuleAreRefused(mixed $tabs, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->types->sanitizeSettings('tabs', $tabs === null ? [] : ['tabs' => $tabs]);
	}

	private static function widget(string $type, ?int $parentIndex = null, ?string $parentTab = null, ?int $id = null, ?int $parentId = null): WidgetInput {
		$settings = $type === GridWidgetTypes::TYPE_TABS ? ['tabs' => [['key' => 'a', 'title' => 'A'], ['key' => 'b', 'title' => 'B']]] : [];
		return new WidgetInput($id, $type, null, null, null, 0, 0, 6, 2, $settings, null, $parentIndex, $parentTab, $parentId);
	}

	public function testWidgetsOnThePageAndInATabOfATabsWidgetAreAccepted(): void {
		$widgets = [
			self::widget('text'),
			self::widget('tabs'),
			self::widget('text', 1, 'b'),
			self::widget('data', 1, 'a'),
		];

		GridWidgetTypes::validateParents($widgets);
		foreach (array_keys($widgets) as $index) {
			GridWidgetTypes::validateParent($index, $widgets);
		}
		$this->addToAssertionCount(1);
	}

	public static function misplacedWidgets(): array {
		return [
			'tab without a tabs widget' => [[self::widget('text', null, 'a')], 0, 'Widget 0 names a tab but no tabs widget'],
			'parent not a tabs widget' => [[self::widget('text'), self::widget('text', 0, 'a')], 1, 'Widget 1 must sit in a tabs widget of the same page'],
			'parent not in the list' => [[self::widget('tabs'), self::widget('text', 5, 'a')], 1, 'Widget 1 must sit in a tabs widget of the same page'],
			'its own parent' => [[self::widget('tabs', 0, 'a')], 0, 'Widget 0 must sit in a tabs widget of the same page'],
			'tabs in a tab' => [[self::widget('tabs'), self::widget('tabs', 0, 'a')], 1, 'Widget 1 would nest tabs more than one level deep'],
			'parent sits in a tab' => [[self::widget('tabs'), self::widget('tabs', 0, 'a'), self::widget('text', 1, 'a')], 2, 'Widget 2 would nest tabs more than one level deep'],
			'unknown tab key' => [[self::widget('tabs'), self::widget('text', 0, 'c')], 1, 'Widget 1 must name the key of one of the tabs'],
			'no tab key' => [[self::widget('tabs'), self::widget('text', 0)], 1, 'Widget 1 must name the key of one of the tabs'],
			'unresolved parentId' => [[self::widget('tabs', null, null, 10), self::widget('text', null, 'a', null, 10)], 1, 'Widget 1 names its tabs widget by an id that was not resolved'],
		];
	}

	/**
	 * @dataProvider misplacedWidgets
	 * @param list<WidgetInput> $widgets
	 */
	public function testAWidgetThatSitsWhereItCannotIsRefused(array $widgets, int $index, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		GridWidgetTypes::validateParent($index, $widgets);
	}

	public function testEveryWidgetOfAListIsChecked(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Widget 2 must name the key of one of the tabs');
		GridWidgetTypes::validateParents([self::widget('tabs'), self::widget('text', 0, 'a'), self::widget('text', 0, 'c')]);
	}

	public function testAParentIdBecomesThePositionOfThatWidget(): void {
		$text = self::widget('text', null, 'b', 12, 10);
		$onPage = self::widget('header', null, null, 11);
		$tabs = self::widget('tabs', null, null, 10);

		$resolved = GridWidgetTypes::resolveParents([$text, $onPage, $tabs]);

		$this->assertSame([2, 'b', null, 12], [$resolved[0]->parentIndex, $resolved[0]->parentTab, $resolved[0]->parentId, $resolved[0]->id]);
		$this->assertSame($onPage, $resolved[1]);
		$this->assertSame($tabs, $resolved[2]);
		GridWidgetTypes::validateParents($resolved);
	}

	public function testAParentIdOfAWidgetNotInTheListIsRefused(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Widget 1 names a tabs widget that is not in the list');
		GridWidgetTypes::resolveParents([self::widget('tabs', null, null, 10), self::widget('text', null, 'a', null, 11)]);
	}
}
