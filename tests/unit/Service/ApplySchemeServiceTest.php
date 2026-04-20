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
use OCA\Tables\Errors\PermissionError;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ApplySchemeServiceTest extends TestCase {
	private ApplySchemeService $service;
	private TableService $tableService;
	private ColumnService $columnService;
	private ViewService $viewService;
	private IDBConnection $dbc;
	private PermissionsService $permissionsService;

	protected function setUp(): void {
		parent::setUp();
		$logger = $this->createMock(LoggerInterface::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->tableService = $this->createMock(TableService::class);
		$this->columnService = $this->createMock(ColumnService::class);
		$this->viewService = $this->createMock(ViewService::class);
		$this->dbc = $this->createMock(IDBConnection::class);

		// Make atomic() execute the callback directly
		$this->dbc->method('beginTransaction')->willReturnSelf();
		$this->dbc->method('commit')->willReturnSelf();

		$this->service = new ApplySchemeService(
			$logger,
			'admin',
			$this->permissionsService,
			$this->tableService,
			$this->columnService,
			$this->viewService,
			$this->dbc,
		);
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

	private function makeTable(): Table {
		$table = new Table();
		$table->setId(1);
		$table->setTitle('T');
		$table->setEmoji('');
		$table->setDescription('');
		return $table;
	}

	private function makeView(int $id, string $title): View {
		$view = new View();
		$view->setId($id);
		$view->setTableId(1);
		$view->setTitle($title);
		$view->setEmoji(null);
		$view->setDescription(null);
		$view->setFilter([]);
		$view->setSort([]);
		$view->setColumnSettings([]);
		return $view;
	}

	private function baseScheme(array $columns = [], array $views = []): array {
		return [
			'title' => 'T',
			'emoji' => '',
			'description' => '',
			'columns' => $columns,
			'views' => $views,
		];
	}

	private function srcCol(int $id, string $title, string $type, array $extra = []): array {
		return array_merge(['id' => $id, 'title' => $title, 'type' => $type], $extra);
	}

	// --- Validation tests (pre-transaction) ---

	public function testMissingColumnsKeyThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->apply(1, ['views' => []], []);
	}

	public function testColumnsUpdateWithNonWhitelistedFieldThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->apply(1, $this->baseScheme([$this->srcCol(1, 'A', 'text')]), [
			'columnsUpdate' => [1 => ['tableId']],
		]);
	}

	public function testViewsUpdateWithNonWhitelistedFieldThrows(): void {
		$this->expectException(BadRequestError::class);
		$this->service->apply(1, $this->baseScheme([], [['title' => 'V', 'filter' => [], 'sort' => []]]), [
			'viewsUpdate' => ['V' => ['createdBy']],
		]);
	}

	public function testColumnsUpdateArrayExceedingLimitThrows(): void {
		$columnsUpdate = [];
		for ($i = 1; $i <= 501; $i++) {
			$columnsUpdate[$i] = ['title'];
		}
		$this->expectException(BadRequestError::class);
		$this->service->apply(1, $this->baseScheme(), ['columnsUpdate' => $columnsUpdate]);
	}

	public function testInvalidRegexPatternInColumnsAddThrows(): void {
		$scheme = $this->baseScheme([
			$this->srcCol(1, 'Notes', 'text', ['textAllowedPattern' => '(a+)+']),
		]);
		// Valid regex but let's use a clearly invalid one
		$scheme['columns'][0]['textAllowedPattern'] = '[invalid';
		$this->expectException(BadRequestError::class);
		$this->service->apply(1, $scheme, ['columnsAdd' => [1]]);
	}

	public function testStringTargetIdIsCastToIntForOwnedColumnCheck(): void {
		$col = $this->makeColumn(7, 'A', 'text');
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([$col]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]);
		$newCol = $this->makeColumn(99, 'A', 'text');
		$this->columnService->method('update')->willReturn($newCol);

		$scheme = $this->makeSchemeForColumnScheme($col);
		$tableScheme = $this->createMock(\OCA\Tables\Model\TableScheme::class);
		$tableScheme->method('jsonSerialize')->willReturn(['id' => 1]);
		$this->tableService->method('getScheme')->willReturn($tableScheme);

		// String "7" should be cast to int 7, matching the owned column
		$result = $this->service->apply(1, $scheme, ['columnsUpdate' => ['7' => ['description']]]);
		self::assertIsArray($result);
	}

	public function testForeignTargetIdInColumnsUpdateThrowsPermissionError(): void {
		$col = $this->makeColumn(5, 'A', 'text');
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([$col]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]);

		$this->expectException(PermissionError::class);
		$this->service->apply(1, $this->baseScheme([$this->srcCol(99, 'X', 'text')]), [
			'columnsUpdate' => [999 => ['description']],
		]);
	}

	public function testForeignTargetIdInColumnsDeleteThrowsPermissionError(): void {
		$col = $this->makeColumn(5, 'A', 'text');
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([$col]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]);

		$this->expectException(PermissionError::class);
		$this->service->apply(1, $this->baseScheme([$this->srcCol(99, 'X', 'text')]), [
			'columnsDelete' => [999],
		]);
	}

	public function testForeignViewTitleInViewsUpdateThrowsNotFoundError(): void {
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]); // no views owned

		$this->expectException(\OCA\Tables\Errors\NotFoundError::class);
		$this->service->apply(1, $this->baseScheme([], [['title' => 'Ghost', 'filter' => [], 'sort' => []]]), [
			'viewsUpdate' => ['Ghost' => ['filter']],
		]);
	}

	public function testEmptySelectionIsNoOp(): void {
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]);
		$tableScheme = $this->createMock(\OCA\Tables\Model\TableScheme::class);
		$tableScheme->method('jsonSerialize')->willReturn(['id' => 1, 'columns' => [], 'views' => []]);
		$this->tableService->method('getScheme')->willReturn($tableScheme);

		$result = $this->service->apply(1, $this->baseScheme(), []);
		self::assertIsArray($result);
		self::assertSame(1, $result['id']);
	}

	public function testAddColumnCallsColumnServiceCreate(): void {
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]);

		$newCol = $this->makeColumn(10, 'Score', 'number');
		$this->columnService->expects(self::once())->method('create')->willReturn($newCol);

		$tableScheme = $this->createMock(\OCA\Tables\Model\TableScheme::class);
		$tableScheme->method('jsonSerialize')->willReturn(['id' => 1]);
		$this->tableService->method('getScheme')->willReturn($tableScheme);

		$this->service->apply(1, $this->baseScheme([$this->srcCol(5, 'Score', 'number')]), [
			'columnsAdd' => [5],
		]);
	}

	public function testAddViewWithColumnRemapping(): void {
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]);

		$createdCol = $this->makeColumn(20, 'Score', 'number');
		$this->columnService->method('create')->willReturn($createdCol);

		$createdView = $this->makeView(5, 'Score View');
		$this->viewService->method('create')->willReturn($createdView);

		$capturedUpdate = null;
		$this->viewService->method('update')->willReturnCallback(function ($viewId, $updateInput) use (&$capturedUpdate) {
			$capturedUpdate = $updateInput;
			return $this->makeView(5, 'Score View');
		});

		$tableScheme = $this->createMock(\OCA\Tables\Model\TableScheme::class);
		$tableScheme->method('jsonSerialize')->willReturn(['id' => 1]);
		$this->tableService->method('getScheme')->willReturn($tableScheme);

		$srcColId = 7;
		$this->service->apply(1,
			$this->baseScheme(
				[$this->srcCol($srcColId, 'Score', 'number')],
				[['title' => 'Score View', 'filter' => [[['columnId' => $srcColId, 'operator' => 'is-not-empty', 'value' => '']]], 'sort' => [], 'columns' => [], 'columnSettings' => []]],
			),
			[
				'columnsAdd' => [$srcColId],
				'viewsAdd' => ['Score View'],
			]
		);

		// The update call should have remapped columnId from srcColId to 20
		self::assertNotNull($capturedUpdate);
	}

	public function testViewFilterReferencesUnknownSourceColumnThrows(): void {
		$table = $this->makeTable();
		$this->columnService->method('findAllByTable')->willReturn([]);
		$this->tableService->method('find')->willReturn($table);
		$this->viewService->method('findAll')->willReturn([]);
		$this->viewService->method('create')->willReturn($this->makeView(5, 'V'));

		$this->expectException(BadRequestError::class);
		$this->service->apply(1,
			$this->baseScheme(
				[],
				[['title' => 'V', 'filter' => [[['columnId' => 999, 'operator' => 'is-not-empty', 'value' => '']]], 'sort' => [], 'columns' => [], 'columnSettings' => []]],
			),
			['viewsAdd' => ['V']]
		);
	}

	// Helper to build a scheme with a column so update can find source data
	private function makeSchemeForColumnScheme(Column $col): array {
		return [
			'title' => 'T',
			'emoji' => '',
			'description' => '',
			'columns' => [
				['id' => 99, 'title' => $col->getTitle(), 'type' => $col->getType(), 'description' => 'new desc'],
			],
			'views' => [],
		];
	}
}
