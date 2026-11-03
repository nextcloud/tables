<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PageService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DbErrorStub;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private const START_PAGE = 1;
	private const GRID_PAGE = 2;
	private const NODE_REL = 20;

	private PageService $service;
	private Context $context;
	private PageMapper&MockObject $pageMapper;
	private PageContentMapper&MockObject $pageContentMapper;
	private WidgetService&MockObject $widgetService;
	private PermissionsService&MockObject $permissionsService;

	protected function setUp(): void {
		parent::setUp();
		$this->context = new Context();
		$this->context->id = 7;
		$this->context->setNodes([self::NODE_REL => ['id' => self::NODE_REL, 'node_id' => 3, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1]]);
		$this->context->setPages([
			self::START_PAGE => ['id' => self::START_PAGE, 'page_type' => Page::TYPE_STARTPAGE, 'technical_name' => 'dashboard', 'title' => null, 'icon' => null, 'description' => null, 'content' => []],
			self::GRID_PAGE => ['id' => self::GRID_PAGE, 'page_type' => Page::TYPE_GRID, 'technical_name' => 'overview', 'title' => 'Overview', 'icon' => 'star', 'description' => null, 'content' => []],
		]);
		$contextMapper = $this->createMock(ContextMapper::class);
		$contextMapper->method('findById')->willReturnCallback(function (int $contextId): Context {
			if ($contextId !== 7) {
				throw new NotFoundError('Context does not exist');
			}
			return $this->context;
		});
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$this->widgetService = $this->createMock(WidgetService::class);
		$this->widgetService->method('findIdsByPageId')->willReturnMap([[self::GRID_PAGE, [31, 32]], [self::START_PAGE, []]]);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->service = new PageService(
			$contextMapper,
			$this->pageMapper,
			$this->pageContentMapper,
			$this->widgetService,
			$this->permissionsService,
			new GridWidgetTypes($l10n),
			$this->createMock(IDBConnection::class),
		);
	}

	public function testFindReturnsThePageWithItsWidgets(): void {
		$widget = ['id' => 31, 'type' => 'text', 'title' => null, 'icon' => null, 'description' => null, 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => ['text' => 'Hi'], 'source' => null];
		$this->widgetService->method('findByPageIds')->with([self::GRID_PAGE])->willReturn([self::GRID_PAGE => [$widget]]);

		$this->assertSame([
			'id' => self::GRID_PAGE,
			'contextId' => 7,
			'pageType' => Page::TYPE_GRID,
			'technicalName' => 'overview',
			'title' => 'Overview',
			'icon' => 'star',
			'description' => null,
			'widgets' => [$widget],
		], $this->service->find(7, self::GRID_PAGE, 'alice'));
	}

	public function testAnApplicationOrPageOutsideTheUsersReachIsNotFound(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->expects($this->never())->method('insert');
		$this->pageMapper->expects($this->never())->method('update');
		$this->pageMapper->expects($this->never())->method('deleteByPageId');
		$this->widgetService->expects($this->never())->method('syncPage');

		foreach ([
			'application of no access' => fn () => $this->service->find(8, self::GRID_PAGE, 'mallory'),
			'create in it' => fn () => $this->service->create(8, 'Overview', null, null, null, 'mallory'),
			'update in it' => fn () => $this->service->update(8, self::GRID_PAGE, 'Mine', null, null, null, [], 'mallory'),
			'delete in it' => fn () => $this->service->delete(8, self::GRID_PAGE, 'mallory'),
			'page of another application' => fn () => $this->service->find(7, 99, 'alice'),
			'update it' => fn () => $this->service->update(7, 99, 'Taken over', null, null, null, [], 'alice'),
			'delete it' => fn () => $this->service->delete(7, 99, 'alice'),
		] as $case => $call) {
			try {
				$call();
				$this->fail($case . ' was found.');
			} catch (NotFoundError) {
			}
		}
	}

	public function testCreateInsertsAGridPageNamedByItsId(): void {
		$this->permissionsService->method('canManageContext')->with($this->context, 'alice')->willReturn(true);
		$inserted = null;
		$this->pageMapper->expects($this->never())->method('insert');
		$this->pageMapper->expects($this->once())->method('insertWithDefaultName')->willReturnCallback(function (Page $page) use (&$inserted): Page {
			$page->id = self::GRID_PAGE;
			$page->setTechnicalName('page_' . self::GRID_PAGE);
			$inserted = $page;
			return $page;
		});

		$this->service->create(7, '  Team Overview ', null, ' home ', '', 'alice');

		$this->assertSame([7, Page::TYPE_GRID, 'Team Overview', 'home', null], [$inserted->getContextId(), $inserted->getPageType(), $inserted->getTitle(), $inserted->getIcon(), $inserted->getDescription()]);
	}

	public function testCreateKeepsAGivenTechnicalName(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->expects($this->never())->method('insertWithDefaultName');
		$inserted = null;
		$this->pageMapper->expects($this->once())->method('insert')->willReturnCallback(function (Page $page) use (&$inserted): Page {
			$page->id = self::GRID_PAGE;
			$inserted = $page;
			return $page;
		});

		$this->service->create(7, 'Overview', 'team_board', null, null, 'alice');

		$this->assertSame('team_board', $inserted->getTechnicalName());
	}

	public function testCreateRefusesAGivenNameAConcurrentSaveTook(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->expects($this->once())->method('insert')
			->willThrowException(DbErrorStub::uniqueViolation());

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('already used');
		$this->service->create(7, 'Overview', 'team_board', null, null, 'alice');
	}

	public function testCreateNeedsATitle(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->expects($this->never())->method('insert');

		$this->expectException(BadRequestError::class);
		$this->service->create(7, '   ', null, null, null, 'alice');
	}

	public function testUpdateSyncsWidgetsById(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$synced = null;
		$this->widgetService->expects($this->once())->method('syncPage')->willReturnCallback(function (int $pageId, array $rows) use (&$synced): void {
			$this->assertSame(self::GRID_PAGE, $pageId);
			$synced = $rows;
		});
		$this->pageMapper->expects($this->never())->method('update');

		$this->service->update(7, self::GRID_PAGE, null, null, null, null, [
			['id' => 31, 'type' => 'header', 'title' => ' Intro ', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2, 'settings' => ['heading' => 'Welcome']],
			['type' => 'data', 'icon' => 'star', 'description' => ' Open tasks ', 'gridX' => 0, 'gridY' => 2, 'gridWidth' => 6, 'gridHeight' => 5, 'source' => ['nodeRelId' => self::NODE_REL]],
			['type' => 'data', 'icon' => '', 'description' => '', 'gridX' => 0, 'gridY' => 7, 'gridWidth' => 12, 'gridHeight' => 5, 'source' => null],
		], 'alice');

		$this->assertSame([
			['id' => 31, 'type' => 'header', 'title' => 'Intro', 'icon' => null, 'description' => null, 'position' => ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2], 'settings' => ['heading' => 'Welcome', 'subheading' => '', 'textAlign' => 'start', 'backgroundColor' => '', 'textColor' => ''], 'nodeRelId' => null],
			['id' => null, 'type' => 'data', 'title' => null, 'icon' => 'star', 'description' => 'Open tasks', 'position' => ['gridX' => 0, 'gridY' => 2, 'gridWidth' => 6, 'gridHeight' => 5], 'settings' => [], 'nodeRelId' => self::NODE_REL],
			['id' => null, 'type' => 'data', 'title' => null, 'icon' => null, 'description' => null, 'position' => ['gridX' => 0, 'gridY' => 7, 'gridWidth' => 12, 'gridHeight' => 5], 'settings' => [], 'nodeRelId' => null],
		], $synced);
	}

	public function testChangesNeedManagePermission(): void {
		$this->permissionsService->method('canManageContext')->with($this->context, 'bob')->willReturn(false);
		$this->pageMapper->expects($this->never())->method('insert');
		$this->pageMapper->expects($this->never())->method('update');
		$this->pageMapper->expects($this->never())->method('deleteByPageId');
		$this->widgetService->expects($this->never())->method('syncPage');

		foreach ([
			'create' => fn () => $this->service->create(7, 'Overview', null, null, null, 'bob'),
			'update' => fn () => $this->service->update(7, self::GRID_PAGE, 'New', null, null, null, [], 'bob'),
			'delete' => fn () => $this->service->delete(7, self::GRID_PAGE, 'bob'),
		] as $case => $call) {
			try {
				$call();
				$this->fail($case . ' was allowed.');
			} catch (PermissionError) {
			}
		}
	}

	public function testUpdateChangesPageFields(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$stored = new Page();
		$stored->id = self::GRID_PAGE;
		$stored->setTechnicalName('overview');
		$stored->setTitle('Overview');
		$stored->setIcon('star');
		$stored->resetUpdatedFields();
		$this->pageMapper->method('findById')->with(self::GRID_PAGE)->willReturn($stored);
		$updated = null;
		$this->pageMapper->expects($this->once())->method('update')->willReturnCallback(function (Page $page) use (&$updated): Page {
			$updated = $page;
			return $page;
		});
		$this->widgetService->expects($this->never())->method('syncPage');

		$this->service->update(7, self::GRID_PAGE, 'Planning', 'planning', '', 'What is next', null, 'alice');

		$this->assertSame([self::GRID_PAGE, 'planning', 'Planning', null, 'What is next'], [$updated->getId(), $updated->getTechnicalName(), $updated->getTitle(), $updated->getIcon(), $updated->getDescription()]);
		$this->assertSame(['title', 'technicalName', 'icon', 'description'], array_keys($updated->getUpdatedFields()));
	}

	public function testUpdateKeepsTheTechnicalNameWhenTheTitleChanges(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$stored = $this->storedGridPage();
		$this->pageMapper->method('findById')->willReturn($stored);
		$this->pageMapper->expects($this->once())->method('update')->willReturnArgument(0);

		$this->service->update(7, self::GRID_PAGE, 'Planning', null, null, null, null, 'alice');

		$this->assertSame('overview', $stored->getTechnicalName());
	}

	public function testAPageKeepsOrTakesItsOwnNames(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$stored = $this->storedGridPage();
		$this->pageMapper->method('findById')->willReturn($stored);
		$this->pageMapper->expects($this->once())->method('update')->willReturnArgument(0);

		$this->service->update(7, self::GRID_PAGE, null, 'overview', null, null, null, 'alice');
		$this->service->update(7, self::GRID_PAGE, null, 'page_' . self::GRID_PAGE, null, null, null, 'alice');

		$this->assertSame('page_' . self::GRID_PAGE, $stored->getTechnicalName());
	}

	public static function refusedTechnicalNames(): array {
		return [
			'upper case' => ['Board', 'lower case letters'],
			'leading digit' => ['2026_plan', 'lower case letters'],
			'empty' => ['', 'lower case letters'],
			'too long' => [str_repeat('a', 201), 'lower case letters'],
			'taken' => ['dashboard', 'already used'],
			'reserved' => ['page_9', 'reserved for automatic names'],
		];
	}

	/**
	 * @dataProvider refusedTechnicalNames
	 */
	public function testUpdateRefusesAnInvalidOrTakenTechnicalName(string $technicalName, string $message): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->method('findById')->willReturn($this->storedGridPage());
		$this->pageMapper->expects($this->never())->method('update');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->service->update(7, self::GRID_PAGE, null, $technicalName, null, null, null, 'alice');
	}

	public function testUpdateRefusesANameAConcurrentSaveTook(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->method('findById')->willReturn($this->storedGridPage());
		$this->pageMapper->method('update')
			->willThrowException(DbErrorStub::uniqueViolation());

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('already used');
		$this->service->update(7, self::GRID_PAGE, null, 'planning', null, null, null, 'alice');
	}

	public function testUpdateRefusesAnUnknownIcon(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->method('findById')->willReturn($this->storedGridPage());
		$this->pageMapper->expects($this->never())->method('update');

		$this->expectException(BadRequestError::class);
		$this->service->update(7, self::GRID_PAGE, null, null, 'no-such-icon', null, null, 'alice');
	}

	private function storedGridPage(): Page {
		$stored = new Page();
		$stored->id = self::GRID_PAGE;
		$stored->setContextId(7);
		$stored->setTechnicalName('overview');
		$stored->setTitle('Overview');
		$stored->resetUpdatedFields();
		return $stored;
	}

	public static function invalidWidgets(): array {
		$at = ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2];
		$foreignSource = 'must be {nodeRelId} of a table or view of this application';
		return [
			'not an object' => [['x'], 'Widget 0 must be an object'],
			'no type' => [[$at], 'Widget 0 needs a type'],
			'unknown type' => [[['type' => 'chart'] + $at], 'Unknown widget type'],
			'id of another page' => [[['id' => 99, 'type' => 'text'] + $at], 'Widget 0 has the id of no other widget on this page'],
			'id twice' => [[['id' => 31, 'type' => 'text'] + $at, ['id' => 31, 'type' => 'text'] + $at], 'Widget 1 has the id'],
			'invalid setting' => [[['type' => 'header', 'settings' => ['heading' => '']] + $at], 'header.heading is required'],
			'outside the grid' => [[['type' => 'text', 'gridX' => 8, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]], 'does not fit in 12 columns'],
			'invalid title' => [[['type' => 'text', 'title' => 5] + $at], 'The title of widget 0'],
			'unknown icon' => [[['type' => 'text', 'icon' => 'no-such-icon'] + $at], 'Widget 0 has an unknown icon'],
			'icon not text' => [[['type' => 'text', 'icon' => 5] + $at], 'The icon of widget 0'],
			'source on a header' => [[['type' => 'header', 'settings' => ['heading' => 'A'], 'source' => ['nodeRelId' => self::NODE_REL]] + $at], 'shows no table or view'],
			'node of another application' => [[['type' => 'data', 'source' => ['nodeRelId' => 21]] + $at], $foreignSource],
			'table outside the application' => [[['type' => 'data', 'source' => ['nodeType' => Application::NODE_TYPE_TABLE, 'nodeId' => 3]] + $at], $foreignSource],
			'source not an object' => [[['type' => 'data', 'source' => self::NODE_REL] + $at], $foreignSource],
		];
	}

	/**
	 * @dataProvider invalidWidgets
	 */
	public function testUpdateRejectsInvalidWidgets(array $widgets, string $message): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->widgetService->expects($this->never())->method('syncPage');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, $widgets, 'alice');
	}

	public function testTheStartPageHoldsWidgets(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->widgetService->expects($this->once())->method('syncPage')->with(self::START_PAGE, $this->callback(static fn (array $rows): bool => array_column($rows, 'type') === ['text']));

		$this->service->update(7, self::START_PAGE, null, null, null, null, [
			['type' => 'text', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => ['text' => 'Hello']],
		], 'alice');
	}

	public function testDeleteRemovesAGridPageWithItsWidgets(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->widgetService->method('findByPageIds')->willReturn([]);
		$this->widgetService->expects($this->once())->method('deleteByPageIds')->with([self::GRID_PAGE]);
		$this->pageMapper->expects($this->once())->method('deleteByPageId')->with(self::GRID_PAGE);

		$this->assertSame('Overview', $this->service->delete(7, self::GRID_PAGE, 'alice')['title']);
	}

	public function testTheStartPageCannotBeDeleted(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->expects($this->never())->method('deleteByPageId');

		$this->expectException(BadRequestError::class);
		$this->service->delete(7, self::START_PAGE, 'alice');
	}

	public function testAPageHoldsAtMostTheMaximumNumberOfWidgets(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->widgetService->expects($this->once())->method('syncPage');
		$widgets = [];
		for ($i = 0; $i < GridWidgetTypes::MAX_WIDGETS_PER_PAGE; $i++) {
			$widgets[] = ['type' => 'text', 'gridX' => 0, 'gridY' => $i * 2, 'gridWidth' => 6, 'gridHeight' => 2];
		}
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, $widgets, 'alice');

		$widgets[] = ['type' => 'text', 'gridX' => 6, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2];
		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('at most 100 widgets');
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, $widgets, 'alice');
	}

	/**
	 * A concurrent save removed a listed widget between the check and the sync.
	 */
	public function testAWidgetRemovedByAConcurrentSaveAsksForARetry(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->widgetService->method('syncPage')->willThrowException(new \InvalidArgumentException('Widget 31 is not on page 2'));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('changed by another save');
		$this->service->update(7, self::GRID_PAGE, null, null, null, null, [['id' => 31, 'type' => 'text', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]], 'alice');
	}

	/**
	 * Two saves adding a source to the same widget at once collide on the source key; that
	 * is no taken technical name.
	 */
	public function testAConcurrentCollisionWithoutARenameAsksForARetry(): void {
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->pageMapper->method('findById')->willReturn($this->storedGridPage());
		$this->widgetService->method('syncPage')->willThrowException(DbErrorStub::uniqueViolation());

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('changed by another save');
		$this->service->update(7, self::GRID_PAGE, 'Renamed title', 'overview', null, null, [['id' => 31, 'type' => 'text', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2]], 'alice');
	}
}
