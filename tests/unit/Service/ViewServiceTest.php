<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\ViewUpdateInput;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\FavoritesService;
use OCA\Tables\Service\FederationService;
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
			$this->createMock(IL10N::class),
			$this->createMock(FederationService::class),
			$this->activityManager,
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
			'widgets' => [['id' => 'w-1', 'type' => 'header', 'content' => ['title' => 'Hi', 'backgroundColor' => '#abc']]],
			'layout' => [['id' => 1, 'widgetId' => 'w-1', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2]],
		];

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => json_encode($grid)]), 'alice', true);

		$this->assertSame($grid, $updated->getGridArray());
	}

	public function testUpdateRejectsNonHexWidgetColors(): void {
		$this->expectUpdateOf($this->storedView(null));
		$grid = ['widgets' => [['id' => 'w-1', 'type' => 'header', 'content' => ['backgroundColor' => 'url(evil)']]], 'layout' => []];

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('hex colors');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['grid' => json_encode($grid)]), 'alice', true);
	}

	public function testUpdateRejectsLayoutItemsWithoutPositions(): void {
		$this->expectUpdateOf($this->storedView(null));
		$grid = ['widgets' => [['id' => 'w-1', 'type' => 'text']], 'layout' => [['widgetId' => 'w-1', 'gridX' => 0]]];

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

	public function testUpdateRejectsAnUnknownType(): void {
		$this->expectUpdateOf($this->storedView());

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Unknown view type');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['type' => 'kanban']), 'alice', true);
	}

	public function testUpdateRefusesToTurnATablelessViewIntoATableView(): void {
		$this->expectUpdateOf($this->storedView(null));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('cannot become a table view');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['type' => 'table']), 'alice', true);
	}

	public function testUpdateRejectsAMalformedSlug(): void {
		$this->expectUpdateOf($this->storedView());

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('slug');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['slug' => 'Not A Slug']), 'alice', true);
	}

	public function testUpdateStoresAValidSlugAndType(): void {
		$view = $this->storedView();
		$this->expectUpdateOf($view);

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray(['slug' => 'intake-2', 'type' => 'grid']), 'alice', true);

		$this->assertSame('intake-2', $updated->getSlug());
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

		$created = $this->service->create('Home', '🧩', null, 'alice', null, null, View::TYPE_GRID, 'home', 'Start here');

		$this->assertNull($created->getTableId());
		$this->assertSame('alice', $created->getOwnership());
		$this->assertSame('grid', $created->getType());
		$this->assertSame('home', $created->getSlug());
		$this->assertSame('Start here', $created->getDescription());
		$this->assertSame('view_6', $created->getTechnicalName());
	}

	public function testCreateWithATableStillChecksTheTablePermission(): void {
		$table = new Table();
		$table->setId(4);
		$this->permissionsService->method('canManageTable')->willReturn(false);

		$this->expectException(PermissionError::class);
		$this->service->create('Home', null, $table, 'alice');
	}
}
