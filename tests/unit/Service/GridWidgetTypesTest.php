<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PermissionsService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GridWidgetTypesTest extends TestCase {
	private ViewMapper&MockObject $viewMapper;
	private TableMapper&MockObject $tableMapper;
	private PermissionsService&MockObject $permissionsService;
	private GridWidgetTypes $types;

	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->types = new GridWidgetTypes($l10n, $this->viewMapper, $this->tableMapper, $this->permissionsService);
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
		$types = (new GridWidgetTypes($l10n, $this->viewMapper, $this->tableMapper, $this->permissionsService))->all();

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

	public function testSanitizeWidgetFillsTheConfigurationDefaultsOfTheType(): void {
		$this->assertFalse($this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi']])['configuration']['showTitle']);
		$this->assertTrue($this->types->sanitizeWidget(['id' => 'w-2', 'type' => 'text'])['configuration']['showTitle']);
	}

	public function testSanitizeFillsDefaultsAndDropsUnknownProperties(): void {
		$widget = $this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi', 'stray' => true]]);

		$this->assertSame(['title' => 'Hi', 'subtitle' => '', 'textAlign' => 'left', 'backgroundColor' => '', 'textColor' => ''], $widget['content']);
	}

	public function testSanitizeKeepsAValidTarget(): void {
		$widget = $this->types->sanitizeWidget(['id' => 'w-1', 'type' => 'data', 'content' => ['target' => ['type' => 'view', 'id' => 6, 'extra' => 1]]]);

		$this->assertSame(['target' => ['type' => 'view', 'id' => 6]], $widget['content']);
	}

	public function testSanitizeGridIdentifiesLayoutItemsByTheirWidget(): void {
		$grid = $this->types->sanitizeGrid([
			'widgets' => [['id' => 'w-1', 'type' => 'text']],
			'layout' => [['id' => 7, 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'stray' => true]],
		], 'alice');

		$this->assertSame([['id' => 'w-1', 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]], $grid['layout']);
	}

	public static function invalidGrids(): array {
		$text = ['id' => 'w-1', 'type' => 'text'];
		$place = static fn (array $item): array => $item + ['widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2];
		$widget = static fn (array $widget): array => ['widgets' => [$widget + ['id' => 'w-1']]];
		return [
			'widget without id' => [['widgets' => [['type' => 'text']]], 'string id and type'],
			'unknown widget type' => [$widget(['type' => 'kanban']), 'Unknown widget type'],
			'missing required string' => [$widget(['type' => 'header', 'content' => ['title' => '   ']]), 'header.title is required'],
			'non-hex color' => [$widget(['type' => 'header', 'content' => ['title' => 'Hi', 'backgroundColor' => 'red']]), 'must be a hex color'],
			'unknown enum value' => [$widget(['type' => 'header', 'content' => ['title' => 'Hi', 'textAlign' => 'justify']]), 'textAlign must be one of'],
			'non-boolean is not cast' => [$widget(['type' => 'text', 'configuration' => ['showTitle' => 1]]), 'showTitle must be true or false'],
			'target without id' => [$widget(['type' => 'data', 'content' => ['target' => ['type' => 'table']]]), 'name a table or view by id'],
			'missing required target' => [$widget(['type' => 'data']), 'data.target is required'],
			'non-string text' => [$widget(['type' => 'text', 'content' => ['text' => ['nested']]]), 'text.text must be a string'],
			'widgets not a list' => [['widgets' => ['a' => $text]], '"widgets" must be a list'],
			'two widgets with one id' => [['widgets' => [$text, $text]], 'share the id'],
			'layout item without its widget' => [['widgets' => [], 'layout' => [$place(['widgetId' => 'w-gone'])]], 'which the grid does not have'],
			'negative position' => [['widgets' => [$text], 'layout' => [$place(['gridX' => -1])]], 'gridX'],
			'widget placed twice' => [['widgets' => [$text], 'layout' => [$place([]), $place([])]], 'places widget w-1 twice'],
			'layout item without size' => [['widgets' => [$text], 'layout' => [$place(['gridWidth' => 0])]], '"gridWidth" must be a positive integer'],
		];
	}

	/**
	 * @dataProvider invalidGrids
	 */
	public function testSanitizeGridRejectsInvalidInput(array $grid, string $message): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->types->sanitizeGrid($grid, 'alice');
	}

	private function dataWidget(string $type, int $id): array {
		return ['id' => 'w-1', 'type' => 'data', 'content' => ['target' => ['type' => $type, 'id' => $id]]];
	}

	public function testSanitizeGridKeepsATargetTheUserCanRead(): void {
		$table = new Table();
		$table->setId(4);
		$this->tableMapper->method('findMany')->with([4])->willReturn([4 => $table]);
		$this->permissionsService->method('canReadRowsByElement')->with($table, 'table', 'alice')->willReturn(true);

		$grid = $this->types->sanitizeGrid(['widgets' => [$this->dataWidget('table', 4)]], 'alice');

		$this->assertSame(['type' => 'table', 'id' => 4], $grid['widgets'][0]['content']['target']);
	}

	public function testSanitizeGridRefusesTargetsTheUserCannotUseWithOneMessage(): void {
		$gridView = new View();
		$gridView->setId(9);
		$readableTable = new Table();
		$readableTable->setId(4);
		$this->viewMapper->method('findMany')->willReturn([9 => $gridView]);
		$this->tableMapper->method('findMany')->willReturn([4 => $readableTable]);
		// the owner can read their own grid view, so only the "has a table" check may refuse it
		$this->permissionsService->method('canReadRowsByElement')->willReturnCallback(static fn ($element): bool => $element === $readableTable || $element === $gridView);

		$cases = [
			'a view without a table' => $this->dataWidget('view', 9),
			'a table that does not exist' => $this->dataWidget('table', 5),
			'a view that does not exist' => $this->dataWidget('view', 10),
		];
		foreach ($cases as $case => $widget) {
			try {
				$this->types->sanitizeGrid(['widgets' => [$widget]], 'alice');
				$this->fail($case . ' was accepted');
			} catch (BadRequestError $e) {
				$this->assertSame('A table or view widget can only show a table or view whose rows you can read.', $e->getMessage(), $case);
			}
		}
	}

	public function testSanitizeGridRefusesATableTheUserCannotRead(): void {
		$table = new Table();
		$table->setId(4);
		$this->tableMapper->method('findMany')->willReturn([4 => $table]);
		$this->permissionsService->method('canReadRowsByElement')->willReturn(false);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('whose rows you can read');
		$this->types->sanitizeGrid(['widgets' => [$this->dataWidget('table', 4)]], 'alice');
	}

	public function testSanitizeGridKeepsAnUnchangedTargetTheSaverCannotRead(): void {
		$table = new Table();
		$table->setId(4);
		$this->tableMapper->method('findMany')->willReturn([4 => $table]);
		$this->permissionsService->expects($this->never())->method('canReadRowsByElement');
		$widget = $this->dataWidget('table', 4);

		$grid = $this->types->sanitizeGrid(['widgets' => [$widget]], 'bob', ['widgets' => [$widget]]);

		$this->assertSame(['type' => 'table', 'id' => 4], $grid['widgets'][0]['content']['target']);
		$this->assertArrayNotHasKey('broken', $grid['widgets'][0]);
	}

	public function testSanitizeGridMarksAnUnchangedTargetThatNoLongerExistsAsBroken(): void {
		$this->tableMapper->method('findMany')->willReturn([]);
		$widget = $this->dataWidget('table', 4);

		$grid = $this->types->sanitizeGrid(['widgets' => [$widget]], 'alice', ['widgets' => [$widget]]);

		$this->assertTrue($grid['widgets'][0]['broken']);
		$this->assertSame(['type' => 'table', 'id' => 4], $grid['widgets'][0]['content']['target'], 'the reference is kept');
	}

	public function testSanitizeGridClearsTheBrokenMarkWhenTheTargetIsValidAgain(): void {
		$table = new Table();
		$table->setId(4);
		$this->tableMapper->method('findMany')->willReturn([4 => $table]);
		$widget = $this->dataWidget('table', 4) + ['broken' => true];

		$grid = $this->types->sanitizeGrid(['widgets' => [$widget]], 'alice', ['widgets' => [$widget]]);

		$this->assertArrayNotHasKey('broken', $grid['widgets'][0]);
	}

	public function testSanitizeGridChecksAChangedTargetAgainstTheSaver(): void {
		$table = new Table();
		$table->setId(5);
		$this->tableMapper->method('findMany')->willReturn([5 => $table]);
		$this->permissionsService->method('canReadRowsByElement')->willReturn(false);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('whose rows you can read');
		$this->types->sanitizeGrid(['widgets' => [$this->dataWidget('table', 5)]], 'bob', ['widgets' => [$this->dataWidget('table', 4)]]);
	}
}
