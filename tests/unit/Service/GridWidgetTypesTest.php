<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\GridWidgetTypes;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GridWidgetTypesTest extends TestCase {
	private ViewMapper&MockObject $viewMapper;
	private GridWidgetTypes $types;

	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->types = new GridWidgetTypes($l10n, $this->viewMapper);
	}

	public function testEveryTypeDescribesItselfAndItsProperties(): void {
		foreach ($this->types->all() as $type => $definition) {
			$this->assertSame($type, $definition['type']);
			$this->assertNotSame('', $definition['title']);
			$this->assertGreaterThan(0, $definition['defaultWidth']);
			$this->assertGreaterThan(0, $definition['defaultHeight']);
			foreach ($definition['properties'] as $name => $property) {
				$this->assertContains($property['type'], GridWidgetTypes::PROPERTY_TYPES, $type . '.' . $name);
				$this->assertArrayHasKey('default', $property, $type . '.' . $name);
				if ($property['type'] === 'enum') {
					$this->assertContains($property['default'], array_column($property['options'], 'value'), $type . '.' . $name);
				}
			}
		}
	}

	public function testTitlesAreTranslated(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => 'nl:' . $text);
		$types = (new GridWidgetTypes($l10n, $this->viewMapper))->all();

		$this->assertSame('nl:Header', $types['header']['title']);
		$this->assertSame('nl:Heading', $types['header']['properties']['title']['title']);
		$this->assertSame('nl:Centered', $types['header']['properties']['textAlign']['options'][1]['label']);
	}

	public function testEveryTypeHasATitleAndShowTitleConfiguration(): void {
		foreach ($this->types->all() as $definition) {
			$this->assertSame('string', $definition['configuration']['title']['type']);
			$this->assertSame('boolean', $definition['configuration']['showTitle']['type']);
		}
	}

	public function testSanitizeWidgetSplitsConfigurationFromContent(): void {
		$widget = $this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'text', 'configuration' => ['title' => 'How', 'showTitle' => true, 'stray' => 'x'], 'content' => ['text' => 'it works']]);

		$this->assertSame(['id' => 'w-1', 'type' => 'text', 'configuration' => ['title' => 'How', 'showTitle' => true], 'content' => ['text' => 'it works']], $widget);
	}

	public function testSanitizeWidgetUpgradesTitleAndShowTitleFromTheOldShape(): void {
		$widget = $this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'title' => 'Old', 'showTitle' => true, 'content' => ['title' => 'Hi']]);

		$this->assertSame(['title' => 'Old', 'showTitle' => true], $widget['configuration']);
		$this->assertSame(['id', 'type', 'configuration', 'content'], array_keys($widget));
	}

	public function testSanitizeWidgetFillsTheConfigurationDefaultsOfTheType(): void {
		$this->assertFalse($this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi']])['configuration']['showTitle']);
		$this->assertTrue($this->types->sanitizeWidget(['id' => 'w-2', 'type' => 'text'])['configuration']['showTitle']);
	}

	public function testSanitizeWidgetRejectsAWidgetWithoutIdOrType(): void {
		$this->expectException(BadRequestError::class);
		$this->types->sanitizeWidget(['type' => 'text']);
	}

	public function testUnknownTypeIsRejected(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Unknown widget type');
		$this->types->get('kanban');
	}

	public function testSanitizeFillsDefaultsAndDropsUnknownProperties(): void {
		$widget = $this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi', 'stray' => true]]);

		$this->assertSame(['title' => 'Hi', 'subtitle' => '', 'textAlign' => 'left', 'backgroundColor' => '', 'textColor' => ''], $widget['content']);
	}

	public function testSanitizeRejectsAMissingRequiredString(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('header.title is required');
		$this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'content' => ['title' => '   ']]);
	}

	public function testSanitizeRejectsANonHexColor(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('must be a hex color');
		$this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi', 'backgroundColor' => 'red']]);
	}

	public function testSanitizeRejectsAnUnknownEnumValue(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('textAlign must be one of');
		$this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi', 'textAlign' => 'justify']]);
	}

	public function testSanitizeRejectsANonBooleanInsteadOfCastingIt(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('showTitle must be true or false');
		$this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'text', 'configuration' => ['showTitle' => 1]]);
	}

	public function testSanitizeKeepsAValidTarget(): void {
		$widget = $this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'data', 'content' => ['target' => ['type' => 'view', 'id' => 6, 'extra' => 1]]]);

		$this->assertSame(['target' => ['type' => 'view', 'id' => 6]], $widget['content']);
	}

	public function testSanitizeUpgradesTheOldDataShape(): void {
		$widget = $this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'data', 'content' => ['targetType' => 'table', 'targetId' => '4']]);

		$this->assertSame(['target' => ['type' => 'table', 'id' => 4]], $widget['content']);
	}

	public function testSanitizeRejectsATargetWithoutId(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('name a table or view by id');
		$this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'data', 'content' => ['target' => ['type' => 'table']]]);
	}

	public function testSanitizeRejectsAMissingRequiredTarget(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('data.target is required');
		$this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'data']);
	}

	public function testSanitizeRejectsANonStringText(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('text.text must be a string');
		$this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'text', 'content' => ['text' => ['nested']]]);
	}

	public function testSanitizeGridIdentifiesLayoutItemsByTheirWidget(): void {
		$grid = $this->types->sanitizeGrid([
			'widgets' => [['id' => 'w-1', 'type' => 'text']],
			'layout' => [['id' => 7, 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'stray' => true]],
		]);

		$this->assertSame([['id' => 'w-1', 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]], $grid['layout']);
	}

	public function testSanitizeGridRejectsALayoutItemWithoutItsWidget(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('which the grid does not have');
		$this->types->sanitizeGrid([
			'widgets' => [],
			'layout' => [['widgetId' => 'w-gone', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]],
		]);
	}

	public function testSanitizeGridRejectsTwoWidgetsWithTheSameId(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('share the id');
		$this->types->sanitizeGrid(['widgets' => [['id' => 'w-1', 'type' => 'text'], ['id' => 'w-1', 'type' => 'text']]]);
	}

	public function testSanitizeGridRejectsANegativePosition(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('gridX');
		$this->types->sanitizeGrid([
			'widgets' => [['id' => 'w-1', 'type' => 'text']],
			'layout' => [['widgetId' => 'w-1', 'gridX' => -1, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]],
		]);
	}

	public function testSanitizeGridRejectsAWidgetShowingAViewWithoutTable(): void {
		$gridView = new View();
		$gridView->setId(9);
		$gridView->setTableId(null);
		$this->viewMapper->method('findMany')->with([9])->willReturn([9 => $gridView]);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('which has no table');
		$this->types->sanitizeGrid(['widgets' => [['id' => 'w-1', 'type' => 'data', 'content' => ['target' => ['type' => 'view', 'id' => 9]]]]]);
	}

	public function testSanitizeGridRejectsWidgetsThatAreNotAList(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('"widgets" must be a list');
		$this->types->sanitizeGrid(['widgets' => ['a' => ['id' => 'w-1', 'type' => 'text']]]);
	}
}
