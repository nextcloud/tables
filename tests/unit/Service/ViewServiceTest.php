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

	public function testUpdateWritesEveryParameterToItsOwnColumn(): void {
		$this->expectUpdateOf($this->storedView(4));

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray([
			'sort' => [['columnId' => 3, 'mode' => 'ASC']],
			'type' => 'table',
		]), 'alice', true);

		$this->assertSame('table', $updated->getType());
		$this->assertSame([['columnId' => 3, 'mode' => 'ASC']], $updated->getSortArray());
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

		$updated = $this->service->update(6, ViewUpdateInput::fromInputArray(['technicalName' => 'intake_2', 'type' => 'table']), 'alice', true);

		$this->assertSame('intake_2', $updated->getTechnicalName());
		$this->assertSame('table', $updated->getType());
	}

	public function testUpdateRefusesToTurnAViewWithATableIntoAGridView(): void {
		$this->expectUpdateOf($this->storedView(4));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('cannot become a grid view');
		$this->service->update(6, ViewUpdateInput::fromInputArray(['type' => 'grid']), 'alice', true);
	}

	public function testCreateWithATableRefusesAGridType(): void {
		$table = new Table();
		$table->setId(4);
		$this->mapper->expects($this->never())->method('insert');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('cannot have a table');
		$this->service->create('Home', null, $table, 'alice', null, null, View::TYPE_GRID);
	}

	public function testDeleteAllStandaloneOwnedByDeletesEachOfTheUsersViewsWithoutTable(): void {
		$this->mapper->method('findAllStandaloneOwnedBy')->with('alice')->willReturn([$this->storedView(null), $this->storedView(null)]);
		$this->permissionsService->method('canManageView')->willReturn(true);

		$this->mapper->expects($this->exactly(2))->method('delete');

		$this->service->deleteAllStandaloneOwnedBy('alice');
	}

	public function testImportKeepsTheSourceUuidWhenItIsFree(): void {
		$this->mapper->method('findByUuid')->willReturn(null);
		$this->expectImportInsert();

		$imported = $this->service->importView(null, $this->importData('0199c8a0-1a2b-7c3d-8e4f-123456789abc'), 'alice');

		$this->assertSame('0199c8a0-1a2b-7c3d-8e4f-123456789abc', $imported->getUuid());
	}

	public function testImportGivesAViewWhoseUuidIsTakenAFreshOne(): void {
		$this->mapper->method('findByUuid')->willReturn($this->storedView(null));
		$this->expectImportInsert();

		$imported = $this->service->importView(null, $this->importData('0199c8a0-1a2b-7c3d-8e4f-123456789abc'), 'alice');

		$this->assertNotSame('0199c8a0-1a2b-7c3d-8e4f-123456789abc', $imported->getUuid());
		$this->assertNotNull($imported->getUuid());
	}

	private function expectImportInsert(): void {
		$this->mapper->method('insert')->willReturnCallback(static function (View $view): View {
			$view->setId(9);
			return $view;
		});
		$this->mapper->method('update')->willReturnArgument(0);
	}

	private function importData(string $uuid): array {
		return [
			'uuid' => $uuid, 'title' => 'Home', 'emoji' => null, 'description' => '', 'type' => View::TYPE_GRID,
			'createdAt' => '2026-10-12 10:00:00', 'lastEditAt' => '2026-10-12 10:00:00', 'columnSettings' => [], 'sort' => [], 'filter' => [],
		];
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
