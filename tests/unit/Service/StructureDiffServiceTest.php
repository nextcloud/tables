<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;
use OCA\Tables\Errors\BadRequestError;
use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class StructureDiffServiceTest extends TestCase {
	private StructureDiffService $service;
	private TableService $tableService;
	private ColumnService $columnService;
	private ViewService $viewService;
	private IAppManager $appManager;
	private PermissionsService $permissionsService;

	protected function setUp(): void {
		parent::setUp();
		$logger = $this->createMock(LoggerInterface::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->tableService = $this->createMock(TableService::class);
		$this->columnService = $this->createMock(ColumnService::class);
		$this->viewService = $this->createMock(ViewService::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->appManager->method('getAppVersion')->willReturn('0.9.0');

		$this->service = new StructureDiffService(
			$logger,
			'admin',
			$this->permissionsService,
			$this->tableService,
			$this->columnService,
			$this->viewService,
			$this->appManager,
		);
	}

	private function makeTable(): Table {
		$table = new Table();
		$table->setTitle('Test Table');
		$table->setEmoji('');
		$table->setDescription('');
		return $table;
	}

	private function makeColumn(int $id, string $title, string $type): Column {
		$col = new Column();
		$col->setId($id);
		$col->setTableId(1);
		$col->setTitle($title);
		$col->setType($type);
		$col->setMandatory(false);
		$col->setDescription('');
		return $col;
	}

	private function makeView(string $title): View {
		$view = new View();
		$view->setTableId(1);
		$view->setTitle($title);
		$view->setEmoji(null);
		$view->setDescription(null);
		$view->setFilter([]);
		$view->setSort([]);
		$view->setColumnSettings([]);
		return $view;
	}

	private function baseScheme(array $columns = [], array $views = [], array $extra = []): array {
		return array_merge([
			'columns' => $columns,
			'views' => $views,
		], $extra);
	}

	private function sourceColumn(int $id, string $title, string $type, array $extra = []): array {
		return array_merge(['id' => $id, 'title' => $title, 'type' => $type], $extra);
	}

	// --- Validation tests ---

	public function testMissingColumnsKeyThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->computeDiff(1, ['views' => []]);
	}

	public function testMissingViewsKeyThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->computeDiff(1, ['columns' => []]);
	}

	public function testColumnsNotArrayThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->computeDiff(1, ['columns' => 'not-an-array', 'views' => []]);
	}

	public function testColumnMissingIdThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->computeDiff(1, [
			'columns' => [['title' => 'X', 'type' => 'text']],
			'views' => [],
		]);
	}

	public function testColumnNonIntegerIdThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->computeDiff(1, [
			'columns' => [['id' => '1', 'title' => 'X', 'type' => 'text']],
			'views' => [],
		]);
	}

	public function testColumnsCountExceedingLimitThrows(): void {
		$columns = [];
		for ($i = 1; $i <= 501; $i++) {
			$columns[] = ['id' => $i, 'title' => "Col $i", 'type' => 'text'];
		}
		$this->expectException(BadRequestError::class);
		$this->service->computeDiff(1, ['columns' => $columns, 'views' => []]);
	}

	// --- Column diff tests ---

	public function testAllColumnsIdenticalProducesNoDiff(): void {
		$table = $this->makeTable();
		$col = $this->makeColumn(1, 'Name', 'text');
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([$col]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([
			$this->sourceColumn(1, 'Name', 'text'),
		]));

		self::assertArrayNotHasKey('columns', $result);
	}

	public function testNewColumnInSourceProducesAddAction(): void {
		$table = $this->makeTable();
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([
			$this->sourceColumn(5, 'Score', 'number'),
		]));

		self::assertCount(1, $result['columns']);
		self::assertSame('add', $result['columns'][0]['action']);
		self::assertSame('Score', $result['columns'][0]['column']['title']);
	}

	public function testMatchedColumnWithPropertyDiffProducesUpdateAction(): void {
		$table = $this->makeTable();
		$col = $this->makeColumn(1, 'Name', 'text');
		$col->setDescription('old desc');
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([$col]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([
			$this->sourceColumn(99, 'Name', 'text', ['description' => 'new desc']),
		]));

		self::assertCount(1, $result['columns']);
		$colDiff = $result['columns'][0];
		self::assertSame('update', $colDiff['action']);
		self::assertSame(1, $colDiff['targetId']);
		self::assertArrayHasKey('description', $colDiff['changes']);
	}

	public function testTargetOnlyColumnProducesDeleteAction(): void {
		$table = $this->makeTable();
		$col = $this->makeColumn(3, 'OldCol', 'text');
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([$col]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([]));

		self::assertCount(1, $result['columns']);
		self::assertSame('delete', $result['columns'][0]['action']);
		self::assertSame('OldCol', $result['columns'][0]['column']['title']);
	}

	public function testSameNameDifferentTypeProducesAddAndDelete(): void {
		$table = $this->makeTable();
		$col = $this->makeColumn(1, 'Status', 'text');
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([$col]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([
			$this->sourceColumn(2, 'Status', 'number'),
		]));

		$actions = array_column($result['columns'], 'action');
		self::assertContains('add', $actions);
		self::assertContains('delete', $actions);
	}

	// --- View diff tests ---

	public function testNewViewTitleInSourceProducesAddAction(): void {
		$table = $this->makeTable();
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([], [
			['title' => 'My View', 'filter' => [], 'sort' => [], 'columns' => [], 'columnSettings' => []],
		]));

		self::assertCount(1, $result['views']);
		self::assertSame('add', $result['views'][0]['action']);
		self::assertSame('My View', $result['views'][0]['view']['title']);
	}

	public function testMatchedViewWithFilterDiffProducesUpdateAction(): void {
		$table = $this->makeTable();
		$view = $this->makeView('My View');
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->viewService->method('findAll')->willReturn([$view]);

		$result = $this->service->computeDiff(1, $this->baseScheme([], [
			['title' => 'My View', 'filter' => [['columnId' => 1, 'operator' => 'is-equal', 'value' => 'x']], 'sort' => [], 'columns' => [], 'columnSettings' => []],
		]));

		self::assertCount(1, $result['views']);
		self::assertSame('update', $result['views'][0]['action']);
	}

	public function testNoDiffAnywherePrducesEmptyDiff(): void {
		$table = $this->makeTable();
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([], []));

		self::assertArrayNotHasKey('columns', $result);
		self::assertArrayNotHasKey('views', $result);
		self::assertArrayNotHasKey('tableMeta', $result);
		self::assertArrayNotHasKey('versionWarning', $result);
	}

	public function testVersionMajorMismatchProducesVersionWarning(): void {
		$table = $this->makeTable();
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->viewService->method('findAll')->willReturn([]);
		// appManager is already set to return '0.9.0'; scheme has major 1
		$result = $this->service->computeDiff(1, $this->baseScheme([], [], ['tablesVersion' => '1.0.0']));
		self::assertArrayHasKey('versionWarning', $result);
		self::assertStringContainsString('1.0.0', $result['versionWarning']);
	}

	public function testSameMajorVersionProducesNoVersionWarning(): void {
		$table = $this->makeTable();
		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->viewService->method('findAll')->willReturn([]);

		$result = $this->service->computeDiff(1, $this->baseScheme([], [], ['tablesVersion' => '0.8.5']));
		self::assertArrayNotHasKey('versionWarning', $result);
	}

	public function testSelectionColumnOverrideWithSelectionFilterProducesWarning(): void {
		$table = $this->makeTable();
		$selCol = $this->makeColumn(10, 'Status', 'selection');
		$selCol->setSelectionOptions('[{"id":1,"label":"A"}]');
		$view = $this->makeView('Filter View');
		$view->setFilter([[['columnId' => 99, 'operator' => 'is-equal', 'value' => '@selection-id-1']]]);

		$this->tableService->method('find')->willReturn($table);
		$this->columnService->method('findAllByTable')->willReturn([$selCol]);
		$this->viewService->method('findAll')->willReturn([$view]);

		// Source column same title+type but different options (triggers update), source col ID = 99
		$result = $this->service->computeDiff(1, $this->baseScheme([
			$this->sourceColumn(99, 'Status', 'selection', ['selectionOptions' => [['id' => 2, 'label' => 'B']]]),
		], [
			['title' => 'Filter View', 'filter' => [[['columnId' => 99, 'operator' => 'is-equal', 'value' => '@selection-id-1']]], 'sort' => [], 'columns' => [], 'columnSettings' => []],
		]));

		$viewUpdates = array_filter($result['views'] ?? [], fn ($v) => $v['action'] === 'update');
		self::assertNotEmpty($viewUpdates);
		$viewUpdate = array_values($viewUpdates)[0];
		self::assertTrue($viewUpdate['selectionFilterWarning'] ?? false);
	}
}
