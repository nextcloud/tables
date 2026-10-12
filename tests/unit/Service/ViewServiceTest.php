<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\ViewUpdateInput;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\FederationService;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\RowService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\ViewService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ViewServiceTest extends TestCase {
	private ViewService $service;
	private MockObject $permissionsService;
	private MockObject $mapper;
	private MockObject $activityManager;

	protected function setUp(): void {
		parent::setUp();

		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('preCheckUserId')->willReturnCallback(static fn (?string $userId) => $userId ?? 'alice');
		$this->mapper = $this->createMock(ViewMapper::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$this->service = new ViewService(
			$this->permissionsService,
			$this->createMock(LoggerInterface::class),
			'alice',
			$this->mapper,
			$this->createMock(ShareService::class),
			$this->createMock(RowService::class),
			$this->createMock(UserHelper::class),
			$this->createMock(FavoritesService::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(ContextService::class),
			$l10n,
			$this->createMock(FederationService::class),
			$this->activityManager,
			new GridWidgetTypes($l10n, $this->mapper, $this->createMock(TableMapper::class), $this->permissionsService),
		);
	}

	private function storedView(?int $tableId = 4): View {
		$view = new View();
		$view->setId(6);
		$view->setTitle('Intake home');
		$view->setTableId($tableId);
		$view->setCreatedBy('alice');
		$view->setType($tableId === null ? View::TYPE_GRID : View::TYPE_TABLE);
		$view->resetUpdatedFields();
		return $view;
	}

	private function expectUpdateOf(View $view): void {
		$this->permissionsService->method('canManageView')->willReturn(true);
		$this->mapper->method('find')->with(6)->willReturn($view);
		$this->mapper->method('update')->willReturnArgument(0);
	}

	public function testUpdateStoresAValidGridAsJson(): void {
		$view = $this->storedView(null);
		$this->expectUpdateOf($view);
		$grid = [
			'widgets' => [['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi', 'backgroundColor' => '#abc', 'stray' => 'dropped']]],
			'layout' => [['id' => 1, 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2]],
		];

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => json_encode($grid)]), 'alice', true);

		$stored = $updated->getGridArray();
		$this->assertSame([['id' => 'w-1', 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2]], $stored['layout'], 'a layout item is identified by its widget');
		$this->assertSame('Hi', $stored['widgets'][0]['content']['title']);
		$this->assertSame('#abc', $stored['widgets'][0]['content']['backgroundColor']);
		$this->assertSame('left', $stored['widgets'][0]['content']['textAlign'], 'defaults are filled in');
		$this->assertArrayNotHasKey('stray', $stored['widgets'][0]['content'], 'unknown properties are dropped');
		$this->assertSame(['title' => '', 'showTitle' => false], $stored['widgets'][0]['configuration'], 'the configuration is filled from the type');
	}

	public function testUpdateRefusesAGridOnATableView(): void {
		$this->expectUpdateOf($this->storedView(4));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Only a grid view holds a grid');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => json_encode(['widgets' => [], 'layout' => []])]), 'alice', true);
	}

	public function testUpdateAcceptsTheGridTypeAndAGridInOneRequest(): void {
		$this->expectUpdateOf($this->storedView(4));

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray(['type' => 'grid', 'grid' => json_encode(['widgets' => [], 'layout' => []])]), 'alice', true);

		$this->assertSame(['widgets' => [], 'layout' => []], $updated->getGridArray());
	}

	public function testUpdateRefusesAGridThatCannotBeEncodedInsteadOfWipingIt(): void {
		$this->expectUpdateOf($this->storedView(null));
		$grid = ['widgets' => [['id' => 'w-1', 'type' => 'text', 'content' => ['text' => "\xFF"]]], 'layout' => []];

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('"grid" cannot be stored');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => $grid]), 'alice', true);
	}

	public function testUpdateWritesEveryParameterToItsOwnColumn(): void {
		$this->expectUpdateOf($this->storedView(4));

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray([
			'sort' => [['columnId' => 3, 'mode' => 'ASC']],
			'type' => 'grid',
		]), 'alice', true);

		$this->assertSame('grid', $updated->getType());
		$this->assertSame([['columnId' => 3, 'mode' => 'ASC']], $updated->getSortArray());
	}

	public function testUpdateRejectsNonHexWidgetColors(): void {
		$this->expectUpdateOf($this->storedView(null));
		$grid = ['widgets' => [['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi', 'backgroundColor' => 'url(evil)']]], 'layout' => []];

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('hex color');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => json_encode($grid)]), 'alice', true);
	}

	public function testUpdateRejectsLayoutItemsWithoutPositions(): void {
		$this->expectUpdateOf($this->storedView(null));
		$grid = ['widgets' => [['id' => 'w-1', 'type' => 'text', 'content' => ['text' => 'x']]], 'layout' => [['widgetId' => 'w-1', 'gridX' => 0]]];

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('gridY');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => json_encode($grid)]), 'alice', true);
	}

	public function testUpdateRejectsWidgetsWithoutIdOrType(): void {
		$this->expectUpdateOf($this->storedView(null));
		$grid = ['widgets' => [['type' => 'text']], 'layout' => []];

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('string id and type');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => json_encode($grid)]), 'alice', true);
	}

	public function testUpdateRefusesToTurnATablelessViewIntoATableView(): void {
		$this->expectUpdateOf($this->storedView(null));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('cannot become a table view');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['type' => 'table']), 'alice', true);
	}

	public function testUpdateRejectsAMalformedTechnicalName(): void {
		$this->expectUpdateOf($this->storedView());

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Technical name');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['technicalName' => 'Not A Name']), 'alice', true);
	}

	public function testUpdateStoresAValidTechnicalNameAndType(): void {
		$view = $this->storedView();
		$this->expectUpdateOf($view);

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray(['technicalName' => 'intake_2', 'type' => 'grid']), 'alice', true);

		$this->assertSame('intake_2', $updated->getTechnicalName());
		$this->assertSame('grid', $updated->getType());
	}

	public function testCreateWithoutTableNeedsAGridType(): void {
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('needs a table');
		$this->service->create('Home', null, null, 'alice', null, null, View::TYPE_TABLE);
	}

	public function testCreateWithoutTableNeedsAUser(): void {
		$this->expectException(PermissionError::class);
		$this->service->create('Home', null, null, '', null, null, View::TYPE_GRID);
	}

	public function testCreateWithoutTableIsOwnedByItsCreator(): void {
		$this->mapper->method('insert')->willReturnCallback(static function (View $view): View {
			$view->setId(6);
			return $view;
		});
		$this->mapper->method('update')->willReturnArgument(0);

		$created = $this->service->create('Home', '🧩', null, 'alice', null, null, View::TYPE_GRID, 'Start here');

		$this->assertNull($created->getTableId());
		$this->assertSame('alice', $created->getOwnership());
		$this->assertSame('grid', $created->getType());
		$this->assertSame('Start here', $created->getDescription());
		$this->assertSame('view_6', $created->getTechnicalName(), 'named by its id, so it is unique on every database');
	}

	public function testCreateWithoutTableRefusesAChosenTechnicalName(): void {
		$this->mapper->expects($this->never())->method('insert');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('named by its id');
		$this->service->create('Home', null, null, 'alice', 'home', null, View::TYPE_GRID);
	}

	public function testUpdateRefusesATechnicalNameOnAViewWithoutTable(): void {
		$this->expectUpdateOf($this->storedView(null));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('named by its id');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['technicalName' => 'home']), 'alice', true);
	}

	public function testImportWithoutTableIgnoresTheSourceTechnicalName(): void {
		$this->mapper->method('insert')->willReturnCallback(static function (View $view): View {
			$view->setId(9);
			return $view;
		});
		$this->mapper->method('update')->willReturnArgument(0);

		$imported = $this->service->importView(null, [
			'title' => 'Home', 'emoji' => null, 'description' => '', 'technicalName' => 'home', 'type' => View::TYPE_GRID,
			'createdAt' => '2026-10-12 10:00:00', 'lastEditAt' => '2026-10-12 10:00:00', 'columnSettings' => [], 'sort' => [], 'filter' => [],
		], 'alice');

		$this->assertSame('view_9', $imported->getTechnicalName());
	}

	public function testCreateWithATableStillChecksTheTablePermission(): void {
		$table = new Table();
		$table->setId(4);
		$this->permissionsService->method('canManageTable')->willReturn(false);

		$this->expectException(PermissionError::class);
		$this->service->create('Home', null, $table, 'alice');
	}
}
