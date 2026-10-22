<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use InvalidArgumentException;
use OC\DB\Exceptions\DbalException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\MenuItemInput;
use OCA\Tables\Tests\Unit\Database\DbErrorStub;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ContextServiceTest extends TestCase {
	private ContextService $service;
	private MockObject $contextMapper;
	private MockObject $contextNodeRelMapper;
	private MockObject $pageMapper;
	private MockObject $pageContentMapper;
	private MockObject $widgetService;
	private MockObject $menuItemMapper;
	private MockObject $permissionsService;
	private MockObject $shareService;
	private MockObject $dbConnection;
	private MockObject $tableMapper;
	private MockObject $viewMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$userManager = $this->createMock(IUserManager::class);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->widgetService = $this->createMock(WidgetService::class);
		$this->menuItemMapper = $this->createMock(MenuItemMapper::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$translations = $this->createMock(IL10N::class);
		$translations->method('t')->willReturnCallback(static fn (string $text): string => 'translated ' . $text);
		$this->shareService = $this->createMock(ShareService::class);
		$navigationManager = $this->createMock(INavigationManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);

		$this->service = new ContextService(
			$this->contextMapper,
			$this->contextNodeRelMapper,
			$this->pageMapper,
			$this->pageContentMapper,
			$logger,
			$this->permissionsService,
			$userManager,
			$eventDispatcher,
			$this->dbConnection,
			$this->shareService,
			false,
			$navigationManager,
			$urlGenerator,
			$this->tableMapper,
			$this->viewMapper,
			$this->widgetService,
			new GridWidgetTypes($l10n),
			$this->menuItemMapper,
			$translations,
		);
	}

	public function testUpdateReordersStartPageContentsFromSubmittedNodes(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([
			101 => [
				'id' => 101,
				'node_type' => 0,
				'node_id' => 11,
				'permissions' => 1,
			],
			102 => [
				'id' => 102,
				'node_type' => 0,
				'node_id' => 12,
				'permissions' => 1,
			],
		]);
		$context->setPages([
			301 => [
				'id' => 301,
				'page_type' => Page::TYPE_STARTPAGE,
				'content' => [
					501 => [
						'id' => 501,
						'node_rel_id' => 101,
						'order' => 10,
					],
					502 => [
						'id' => 502,
						'node_rel_id' => 102,
						'order' => 20,
					],
				],
			],
		]);

		$pageContentsById = [
			501 => $this->buildPageContent(501, 301, 101, 10),
			502 => $this->buildPageContent(502, 301, 102, 20),
		];
		$updatedOrders = [];

		$this->contextMapper
			->expects($this->once())
			->method('findById')
			->with(7, 'user-1')
			->willReturn($context);

		$this->pageContentMapper
			->expects($this->exactly(2))
			->method('findById')
			->willReturnCallback(static fn (int $contentId): PageContent => $pageContentsById[$contentId]);

		$this->pageContentMapper
			->expects($this->exactly(2))
			->method('update')
			->willReturnCallback(function (PageContent $pageContent) use (&$updatedOrders): PageContent {
				$updatedOrders[$pageContent->getId()] = $pageContent->getOrder();
				return $pageContent;
			});

		$this->contextMapper
			->expects($this->once())
			->method('update')
			->with($context)
			->willReturn($context);

		$updatedContext = $this->service->update(7, 'user-1', null, null, null, [
			[
				'id' => 12,
				'type' => 0,
				'permissions' => 1,
			],
			[
				'id' => 11,
				'type' => 0,
				'permissions' => 1,
			],
		]);

		self::assertCount(2, $updatedOrders);
		self::assertSame(20, $updatedOrders[501]);
		self::assertSame(10, $updatedOrders[502]);
		self::assertSame(20, $updatedContext->getPages()[301]['content'][501]['order']);
		self::assertSame(10, $updatedContext->getPages()[301]['content'][502]['order']);
	}

	public function testDeleteRemovesTheWidgetsOfEveryPage(): void {
		$context = new Context();
		$context->id = 7;
		$this->contextMapper->method('findById')->with(7, 'user-1')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->with(7)->willReturn([301, 302]);

		$this->widgetService->expects($this->once())->method('deleteByPageIds')->with([301, 302]);
		$this->pageMapper->expects($this->exactly(2))->method('deleteByPageId');
		$this->contextMapper->expects($this->once())->method('delete')->with($context);

		$this->service->delete(7, 'user-1');
	}

	public function testDeleteRemovesTheApplicationBeforeWhatBelongsToIt(): void {
		$context = new Context();
		$context->id = 7;
		$this->contextMapper->method('findById')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->willReturn([301]);
		$calls = [];
		$this->contextMapper->method('delete')->willReturnCallback(static function (Context $deleted) use (&$calls): Context {
			$calls[] = 'application';
			return $deleted;
		});
		$this->menuItemMapper->method('deleteAllByContextId')->with(7)->willReturnCallback(static function () use (&$calls): void {
			$calls[] = 'menu';
		});
		$this->contextNodeRelMapper->method('deleteAllByContextId')->willReturnCallback(static function () use (&$calls): void {
			$calls[] = 'nodes';
		});
		$this->widgetService->method('deleteByPageIds')->willReturnCallback(static function () use (&$calls): void {
			$calls[] = 'widgets';
		});
		$this->pageMapper->method('deleteByPageId')->willReturnCallback(static function () use (&$calls): int {
			$calls[] = 'page';
			return 1;
		});

		$this->service->delete(7, 'alice');

		$this->assertSame(['application', 'menu', 'nodes', 'widgets', 'page'], $calls);
	}

	public function testDeletingANodeUnlinksItsWidgets(): void {
		$this->contextNodeRelMapper->method('getRelIdsForNode')->with(11, 0)->willReturn([101, 102]);

		$this->widgetService->expects($this->once())->method('deleteSourcesByNodeRelIds')->with([101, 102]);
		$this->pageContentMapper->expects($this->once())->method('deleteByNodeRelIds')->with([101, 102]);

		$this->service->deleteNodeRel(11, 0);
	}

	public function testRemovingANodeFromTheContextUnlinksOnlyItsWidgets(): void {
		$relation = new ContextNodeRelation();
		$relation->id = 101;
		$this->contextNodeRelMapper->method('findById')->with(101)->willReturn($relation);
		$this->contextNodeRelMapper->method('delete')->willReturnArgument(0);
		$this->pageContentMapper->method('findByNodeRelation')->willReturn([]);

		$this->widgetService->expects($this->once())->method('deleteSourcesByNodeRelIds')->with([101]);
		$this->widgetService->expects($this->never())->method('deleteByPageIds');
		$this->widgetService->expects($this->never())->method('deleteByIds');

		$this->service->removeNodeFromContextAndPages(101);
	}

	public function testRemovingANodeDeletesItsSourcesFirstInOneTransaction(): void {
		$relation = new ContextNodeRelation();
		$relation->id = 101;
		$calls = [];
		$this->dbConnection->method('beginTransaction')->willReturnCallback(function () use (&$calls): void {
			$calls[] = 'begin';
		});
		$this->dbConnection->method('commit')->willReturnCallback(function () use (&$calls): void {
			$calls[] = 'commit';
		});
		$this->widgetService->method('deleteSourcesByNodeRelIds')->willReturnCallback(function () use (&$calls): void {
			$calls[] = 'sources';
		});
		$this->contextNodeRelMapper->method('findById')->with(101)->willReturn($relation);
		$this->contextNodeRelMapper->method('delete')->willReturnCallback(function (ContextNodeRelation $deleted) use (&$calls): ContextNodeRelation {
			$calls[] = 'relation';
			return $deleted;
		});
		$this->pageContentMapper->method('findByNodeRelation')->willReturnCallback(function () use (&$calls): array {
			$calls[] = 'contents';
			return [];
		});

		$this->service->removeNodeFromContextAndPages(101);

		self::assertSame(['begin', 'sources', 'relation', 'contents', 'commit'], $calls);
	}

	public function testAFailedNodeRemovalRollsBackTheDeletedSources(): void {
		$this->contextNodeRelMapper->method('findById')->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('gone'));
		$this->widgetService->expects($this->once())->method('deleteSourcesByNodeRelIds')->with([101]);
		$this->dbConnection->expects($this->once())->method('rollBack');
		$this->dbConnection->expects($this->never())->method('commit');

		$this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
		$this->service->removeNodeFromContextAndPages(101);
	}

	public function testGetPagesWithWidgetsAddsTheWidgetsOfEachPage(): void {
		$context = new Context();
		$context->setPages([
			301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []],
			302 => ['id' => 302, 'page_type' => Page::TYPE_GRID, 'content' => []],
		]);
		$widget = ['id' => 9, 'type' => 'text', 'title' => null, 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => ['text' => 'Hi'], 'source' => null];
		$this->widgetService->method('findByPageIds')->with([301, 302])->willReturn([302 => [$widget]]);

		$pages = $this->service->getPagesWithWidgets($context);

		self::assertSame([], $pages[301]['widgets']);
		self::assertSame([$widget], $pages[302]['widgets']);
	}

	public function testImportContextRemapsSourcesAndSkipsInvalidWidgets(): void {
		$table = new Table();
		$table->id = 77;
		$table->setOwnership('user-1');
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 8;
			return $context;
		});
		$this->contextNodeRelMapper->method('insert')->willReturnCallback(static function (ContextNodeRelation $relation): ContextNodeRelation {
			$relation->id = 501;
			return $relation;
		});
		$insertedPages = [];
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use (&$insertedPages): Page {
			$page->id = 600 + count($insertedPages);
			$insertedPages[] = $page;
			return $page;
		});

		$insertedContents = [];
		$this->pageContentMapper->method('insert')->willReturnCallback(static function (PageContent $content) use (&$insertedContents): PageContent {
			$insertedContents[] = [$content->getPageId(), $content->getNodeRelId(), $content->getOrder()];
			return $content;
		});

		$inserted = [];
		$this->widgetService->method('insert')->willReturnCallback(function (...$args) use (&$inserted) {
			$inserted[] = $args;
			return new \OCA\Tables\Db\Widget();
		});

		$this->service->importContext($table, [
			'name' => 'Projects',
			'nodes' => [['id' => 101, 'node_id' => 5, 'node_type' => 0, 'permissions' => 1]],
			'pages' => [[
				'id' => 301,
				'page_type' => Page::TYPE_GRID,
				'technical_name' => 'overview',
				'title' => 'Overview',
				'icon' => 'star',
				'description' => 'At a glance',
				'content' => [['node_rel_id' => 101, 'order' => 10], ['node_rel_id' => 999, 'order' => 20]],
				'widgets' => [
					['type' => 'data', 'title' => 'Tasks', 'icon' => 'star', 'description' => 'Open tasks', 'gridX' => 0, 'gridY' => 2, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => ['unknown' => 10], 'source' => ['nodeRelId' => 101]],
					['type' => 'data', 'title' => null, 'gridX' => 0, 'gridY' => 7, 'gridWidth' => 6, 'gridHeight' => 5, 'settings' => [], 'source' => ['nodeRelId' => 999]],
					['type' => 'text', 'title' => null, 'gridX' => 6, 'gridY' => 7, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => ['text' => 'Hi'], 'source' => ['nodeRelId' => 101]],
					['type' => 'header', 'title' => null, 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2, 'settings' => ['heading' => ''], 'source' => null],
					['type' => 'chart', 'title' => null, 'gridX' => 0, 'gridY' => 9, 'gridWidth' => 12, 'gridHeight' => 2, 'settings' => [], 'source' => null],
					['type' => 'text', 'title' => null, 'gridX' => 8, 'gridY' => 11, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => [], 'source' => null],
					['type' => 'text', 'title' => null, 'gridX' => 0, 'gridY' => 11, 'gridWidth' => 6, 'gridHeight' => 101, 'settings' => [], 'source' => null],
					['type' => 'text', 'title' => str_repeat('a', 201), 'gridX' => 0, 'gridY' => 11, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => [], 'source' => null],
					['type' => 'text', 'title' => '  Notes ', 'gridX' => 6, 'gridY' => 11, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => [], 'source' => null],
				],
			]],
		], 5);

		self::assertSame(['overview', 'Overview', 'star', 'At a glance'], [$insertedPages[0]->getTechnicalName(), $insertedPages[0]->getTitle(), $insertedPages[0]->getIcon(), $insertedPages[0]->getDescription()]);
		self::assertCount(4, $inserted);
		self::assertSame(['Notes', ['gridX' => 6, 'gridY' => 11, 'gridWidth' => 6, 'gridHeight' => 2]], [$inserted[3][2], $inserted[3][3]]);
		self::assertSame([[600, 501, 10]], $insertedContents);
		self::assertSame([600, 'data', 'Tasks', ['gridX' => 0, 'gridY' => 2, 'gridWidth' => 12, 'gridHeight' => 5], [], 501, 'star', 'Open tasks'], $inserted[0]);
		self::assertNull($inserted[1][5]);
		self::assertSame([600, 'text', null, ['gridX' => 6, 'gridY' => 7, 'gridWidth' => 6, 'gridHeight' => 2], ['text' => 'Hi'], null, null, null], $inserted[2]);
	}

	public function testANewApplicationGetsAStartPageNamedDashboard(): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 7;
			return $context;
		});
		$inserted = [];
		$this->pageMapper->expects($this->once())->method('insert')
			->willReturnCallback(static function (Page $page) use (&$inserted): Page {
				$page->id = 70;
				$inserted[] = $page;
				return $page;
			});

		$context = $this->service->create('Projects', 'star', '', [], 'user-1', 0);

		self::assertSame(Page::TYPE_STARTPAGE, $inserted[0]->getPageType());
		self::assertSame('dashboard', $inserted[0]->getTechnicalName());
		self::assertSame('dashboard', $context->getPages()[70]['technicalName']);
	}

	public function testImportedPagesKeepFreeNamesAndTheRestGetTheDefault(): void {
		$table = new Table();
		$table->id = 40;
		$table->setOwnership('user-1');
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 8;
			return $context;
		});
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$names = [];
		$pageId = 80;
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use (&$names, &$pageId): Page {
			$page->id = $pageId++;
			$names[$page->getId()] = $page->getTechnicalName();
			return $page;
		});
		$this->pageMapper->method('insertWithDefaultName')->willReturnCallback(static function (Page $page) use (&$names, &$pageId): Page {
			$page->id = $pageId++;
			$page->setTechnicalName('page_' . $page->getId());
			$names[$page->getId()] = $page->getTechnicalName();
			return $page;
		});

		$this->service->importContext($table, [
			'name' => 'Projects',
			'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]],
			'pages' => [
				['id' => 1, 'page_type' => Page::TYPE_STARTPAGE],
				['id' => 2, 'page_type' => Page::TYPE_GRID, 'title' => 'Sales', 'technical_name' => 'sales'],
				['id' => 3, 'page_type' => Page::TYPE_GRID, 'title' => 'More sales', 'technical_name' => 'sales'],
				['id' => 4, 'page_type' => Page::TYPE_GRID, 'title' => 'Other', 'technical_name' => 'Bad Name'],
				['id' => 5, 'page_type' => Page::TYPE_GRID, 'title' => 'Team overview'],
				['id' => 6, 'page_type' => Page::TYPE_STARTPAGE, 'technicalName' => 'home'],
				['id' => 7, 'page_type' => Page::TYPE_GRID, 'title' => 'Copied', 'technical_name' => 'page_2'],
			],
		], 4);

		self::assertSame([
			80 => 'dashboard',
			81 => 'sales',
			82 => 'page_82',
			83 => 'page_83',
			84 => 'page_84',
			85 => 'home',
			86 => 'page_86',
		], $names);
	}

	public function testAnImportedStartPageLeavesDashboardToThePageExportedWithIt(): void {
		$table = new Table();
		$table->id = 40;
		$table->setOwnership('user-1');
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 8;
			return $context;
		});
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$names = [];
		$pageId = 80;
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use (&$names, &$pageId): Page {
			$page->id = $pageId++;
			$names[$page->getId()] = $page->getTechnicalName();
			return $page;
		});
		$this->pageMapper->method('insertWithDefaultName')->willReturnCallback(static function (Page $page) use (&$names, &$pageId): Page {
			$page->id = $pageId++;
			$names[$page->getId()] = 'page_' . $page->getId();
			return $page;
		});

		$this->service->importContext($table, [
			'name' => 'Projects',
			'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]],
			'pages' => [
				['id' => 1, 'page_type' => Page::TYPE_STARTPAGE],
				['id' => 2, 'page_type' => Page::TYPE_GRID, 'title' => 'Board', 'technical_name' => 'dashboard'],
			],
		], 4);

		self::assertSame([80 => 'page_80', 81 => 'dashboard'], $names);
	}

	private function contextWithMenu(int $id, array $menuItems): Context {
		$context = new Context();
		$context->id = $id;
		$context->setName('Intake portal');
		$context->setOwnerId('user-1');
		$context->setOwnerType(Application::OWNER_TYPE_USER);
		$context->setNodes([]);
		$context->setPages([]);
		$context->setMenuItems($menuItems);
		return $context;
	}

	/**
	 * @param list<MenuItem> $inserted collects the stored items
	 */
	private function expectMenuReplacement(int $contextId, array &$inserted): void {
		$this->menuItemMapper->expects($this->once())->method('deleteAllByContextId')->with($contextId);
		$this->menuItemMapper->method('insert')->willReturnCallback(static function (MenuItem $item) use (&$inserted): MenuItem {
			$item->id = count($inserted) + 1;
			$inserted[] = $item;
			return $item;
		});
		$this->menuItemMapper->method('insertWithDefaultName')->willReturnCallback(static function (MenuItem $item) use (&$inserted): MenuItem {
			$item->id = count($inserted) + 1;
			$item->setTechnicalName('item_' . $item->getId());
			$inserted[] = $item;
			return $item;
		});
		$this->menuItemMapper->method('findByContextId')->willReturnCallback(static function () use (&$inserted): array {
			return $inserted;
		});
	}

	private function expectExistingTargets(array $views, array $tables): void {
		$this->viewMapper->method('findMany')->willReturnCallback(static fn (array $ids): array => array_intersect_key($views, array_flip($ids)));
		$this->tableMapper->method('findMany')->willReturnCallback(static fn (array $ids): array => array_intersect_key($tables, array_flip($ids)));
	}

	private function expectNodeInsertion(array &$relations): void {
		$this->contextNodeRelMapper->method('insert')->willReturnCallback(static function (ContextNodeRelation $relation) use (&$relations): ContextNodeRelation {
			$relation->id = 200 + count($relations);
			$relations[] = $relation;
			return $relation;
		});
	}

	public function testUpdateReplacesTheMenuInOrderAndNamesUnnamedItemsByTheirId(): void {
		$context = $this->contextWithMenu(7, []);
		$this->contextMapper->method('findById')->with(7, 'user-1')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->expectExistingTargets([6 => new View()], [4 => new Table()]);
		$relations = [];
		$this->expectNodeInsertion($relations);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$updated = $this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Intake home', 'targetType' => 'view', 'targetId' => 6, 'technicalName' => 'home'],
			['label' => 'Welcome table!', 'targetType' => 'table', 'targetId' => 4],
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://docs.nextcloud.com'],
		]));

		$this->assertSame([10, 20, 30], array_map(static fn (MenuItem $item) => $item->getOrder(), $inserted));
		$this->assertSame(['home', 'item_2', 'item_3'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
		$this->assertSame([6, 4, null], array_map(static fn (MenuItem $item) => $item->getTargetId(), $inserted));
		$this->assertSame([null, null, 'https://docs.nextcloud.com'], array_map(static fn (MenuItem $item) => $item->getUrl(), $inserted));
		$this->assertCount(3, $updated->getMenuItems());
		$this->assertSame('Welcome table!', $updated->getMenuItems()[1]['label']);
	}

	public function testMenuTargetsBecomeNodesSoRecipientsCanOpenThem(): void {
		$context = $this->contextWithMenu(7, []);
		$context->setNodes([101 => ['id' => 101, 'node_id' => 4, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1]]);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->expectExistingTargets([6 => new View()], [4 => new Table()]);
		$relations = [];
		$this->expectNodeInsertion($relations);
		$this->pageContentMapper->method('findByPageAndNodeRelation')->willReturn(null);
		$this->pageContentMapper->method('insert')->willReturnCallback(static function (PageContent $content): PageContent {
			$content->id = 501;
			return $content;
		});
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$updated = $this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Home', 'targetType' => 'view', 'targetId' => 6],
			['label' => 'Requests', 'targetType' => 'table', 'targetId' => 4],
		]));

		// the table already is a node, only the view is added, with read permission
		$this->assertCount(1, $relations);
		$this->assertSame([6, Application::NODE_TYPE_VIEW, Application::PERMISSION_READ], [$relations[0]->getNodeId(), $relations[0]->getNodeType(), $relations[0]->getPermissions()]);
		// a node added by a save comes back as its relation, as the update has always returned it
		$this->assertSame(['id' => 200, 'contextId' => 7, 'nodeId' => 6, 'nodeType' => Application::NODE_TYPE_VIEW, 'permissions' => Application::PERMISSION_READ], $updated->getNodes()[200]);
		$this->assertSame(200, $updated->getPages()[301]['content'][501]['nodeRelId']);
	}

	public static function unusableNewTargets(): array {
		return [
			'exists but cannot be managed' => [[4 => new Table()]],
			'does not exist' => [[]],
		];
	}

	/**
	 * Both get the same answer, so a save reveals nothing about which ids exist.
	 *
	 * @dataProvider unusableNewTargets
	 */
	public function testANewMenuTargetThatCannotBeUsedIsRefusedTheSameWay(array $tables): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->expectExistingTargets([], $tables);
		$this->permissionsService->method('canManageNodeById')->willReturn(false);
		$this->menuItemMapper->expects($this->never())->method('deleteAllByContextId');

		$this->expectException(PermissionError::class);
		$this->expectExceptionMessage('Owner cannot manage node 4 (type 0)');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Requests', 'targetType' => 'table', 'targetId' => 4],
		]));
	}

	public function testAStoredMenuTargetThatNoLongerExistsIsKeptWithoutANode(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, [
			['targetType' => 'view', 'targetId' => 99, 'url' => null, 'technicalName' => 'gone'],
		]));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->expectExistingTargets([], []);
		$this->contextNodeRelMapper->expects($this->never())->method('insert');
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Gone', 'targetType' => 'view', 'targetId' => 99],
		]));

		$this->assertSame([99], array_map(static fn (MenuItem $item) => $item->getTargetId(), $inserted));
	}

	public function testAnItemSentWithoutANameKeepsTheStoredNameOfItsTarget(): void {
		$context = $this->contextWithMenu(7, [
			['targetType' => 'view', 'targetId' => 6, 'url' => null, 'technicalName' => 'home'],
			['targetType' => 'url', 'targetId' => null, 'url' => 'https://example.org/a', 'technicalName' => 'docs'],
			['targetType' => 'url', 'targetId' => null, 'url' => 'https://example.org/b', 'technicalName' => 'docs_2'],
		]);
		$context->setNodes([101 => ['id' => 101, 'node_id' => 6, 'node_type' => Application::NODE_TYPE_VIEW, 'permissions' => 1]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		// the view is renamed, the two links swap places and a new link is added
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Start', 'targetType' => 'view', 'targetId' => 6],
			['label' => 'Docs', 'url' => 'https://example.org/b'],
			['label' => 'Docs', 'url' => 'https://example.org/a'],
			['label' => 'Docs', 'url' => 'https://example.org/c'],
		]));

		$this->assertSame(['home', 'docs_2', 'docs', 'item_4'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
	}

	public function testUnnamedItemsAreNamedByTheirIdBesideGivenNames(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'url' => 'https://example.org/a'],
			['label' => 'Docs', 'url' => 'https://example.org/b'],
			['label' => 'Other', 'url' => 'https://example.org/c', 'technicalName' => 'docs_2'],
		]));

		$this->assertSame(['item_1', 'item_2', 'docs_2'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
	}

	public function testTwoMenuItemsWithTheSameTechnicalNameAreRefused(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('share the technical name "home"');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'A', 'url' => 'https://example.org/a', 'technicalName' => 'home'],
			['label' => 'B', 'url' => 'https://example.org/b', 'technicalName' => 'home'],
		]));
	}

	public function testAGivenNameOfTheAutomaticFormIsRefused(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, [
			['targetType' => 'url', 'targetId' => null, 'url' => 'https://example.org/a', 'technicalName' => 'item_3'],
		]));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->never())->method('deleteAllByContextId');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The technical name item_3 is reserved for automatic names.');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'B', 'url' => 'https://example.org/b', 'technicalName' => 'item_3'],
		]));
	}

	public function testAnItemSentBackWithItsStoredAutomaticNameKeepsIt(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, [
			['targetType' => 'url', 'targetId' => null, 'url' => 'https://example.org/a', 'technicalName' => 'item_3'],
		]));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'A', 'url' => 'https://example.org/a', 'technicalName' => 'item_3'],
		]));

		$this->assertSame(['item_3'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
	}

	public function testUpdateRunsAgainWhenTheDatabaseAsksForARetry(): void {
		$locked = $this->createMock(DbalException::class);
		$locked->method('isRetryable')->willReturn(true);
		$this->contextMapper->expects($this->exactly(2))->method('findById')
			->willReturnOnConsecutiveCalls($this->throwException($locked), $this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);

		$updated = $this->service->update(7, 'user-1', 'Renamed', null, null, null);

		$this->assertSame('Renamed', $updated->getName());
	}

	public function testUpdateLeavesTheMenuAloneWhenNoneIsGiven(): void {
		$context = $this->contextWithMenu(7, [['id' => 1, 'label' => 'Keep me']]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->never())->method('deleteAllByContextId');

		$updated = $this->service->update(7, 'user-1', 'Renamed', null, null, null);

		$this->assertSame('Renamed', $updated->getName());
		$this->assertSame('Keep me', $updated->getMenuItems()[0]['label']);
	}

	public function testCreateStoresTheMenuItems(): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 8;
			return $context;
		});
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page): Page {
			$page->id = 3;
			return $page;
		});
		$inserted = [];
		$this->expectMenuReplacement(8, $inserted);

		$created = $this->service->create('Intake portal', 'heart', ' desc ', [], 'user-1', 0, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://example.org'],
		], allowNewStartPage: true));

		$this->assertSame('desc', $created->getDescription());
		$this->assertCount(1, $created->getMenuItems());
		$this->assertSame(['item_1'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
	}

	/**
	 * @param list<MenuItem> $inserted collects the stored items
	 */
	private function expectCreation(int $contextId, int $startPageId, array &$inserted): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context) use ($contextId): Context {
			$context->id = $contextId;
			return $context;
		});
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use ($startPageId): Page {
			$page->id = $startPageId;
			return $page;
		});
		$this->expectMenuReplacement($contextId, $inserted);
	}

	/**
	 * @param list<MenuItem> $items
	 * @return list<array{string, string, ?int, string, ?string}>
	 */
	private static function describeMenu(array $items): array {
		return array_map(static fn (MenuItem $item): array => [
			$item->getLabel(), $item->getTargetType(), $item->getTargetId(), $item->getTechnicalName(), $item->getIcon(),
		], $items);
	}

	public function testCreateWithoutAMenuAddsADashboardItemForTheStartPage(): void {
		$inserted = [];
		$this->expectCreation(8, 3, $inserted);

		$created = $this->service->create('Intake portal', 'heart', '', [], 'user-1', 0);

		$this->assertSame([['translated Dashboard', 'page', 3, 'dashboard', null]], self::describeMenu($inserted));
		$this->assertSame(3, $created->getMenuItems()[0]['targetId']);
	}

	public function testCreateWithAnEmptyMenuStoresNoItems(): void {
		$inserted = [];
		$this->expectCreation(8, 3, $inserted);

		$created = $this->service->create('Intake portal', 'heart', '', [], 'user-1', 0, []);

		$this->assertSame([], $inserted);
		$this->assertSame([], $created->getMenuItems());
	}

	public function testCreateStoresAMenuWithoutAStartPageItemAsSubmitted(): void {
		$inserted = [];
		$this->expectCreation(8, 3, $inserted);

		$this->service->create('Intake portal', 'heart', '', [], 'user-1', 0, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'url' => 'https://example.org', 'technicalName' => 'dashboard'],
			['label' => 'Guide', 'url' => 'https://example.org/guide'],
		], allowNewStartPage: true));

		$this->assertSame([
			['Docs', 'url', null, 'dashboard', null],
			['Guide', 'url', null, 'item_2', null],
		], self::describeMenu($inserted));
	}

	public function testCreateResolvesAPageItemWithoutAnIdToTheStartPage(): void {
		$inserted = [];
		$this->expectCreation(8, 3, $inserted);

		$this->service->create('Intake portal', 'heart', '', [], 'user-1', 0, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'url' => 'https://example.org'],
			['label' => 'Overview', 'targetType' => 'page', 'icon' => 'home'],
		], allowNewStartPage: true));

		$this->assertSame([
			['Docs', 'url', null, 'item_1', null],
			['Overview', 'page', 3, 'item_2', 'home'],
		], self::describeMenu($inserted));
	}

	public function testAPageOfTheApplicationIsAcceptedAndAddsNoNode(): void {
		$context = $this->contextWithMenu(7, []);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->contextNodeRelMapper->expects($this->never())->method('insert');
		$this->pageContentMapper->expects($this->never())->method('insert');
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$updated = $this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Dashboard', 'targetType' => 'page', 'targetId' => 301],
		]));

		$this->assertSame([['Dashboard', 'page', 301, 'item_1', null]], self::describeMenu($inserted));
		$this->assertSame([], $updated->getNodes());
	}

	public function testAPageOfAnotherApplicationIsRefused(): void {
		$context = $this->contextWithMenu(7, []);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->never())->method('deleteAllByContextId');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('a page of its own application');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Elsewhere', 'targetType' => 'page', 'targetId' => 302],
		]));
	}

	public function testAStoredPageItemWhosePageIsGoneIsKept(): void {
		$context = $this->contextWithMenu(7, [
			['targetType' => 'page', 'targetId' => 302, 'url' => null, 'technicalName' => 'overview', 'broken' => true],
		]);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->contextNodeRelMapper->expects($this->never())->method('insert');
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Overview', 'targetType' => 'page', 'targetId' => 302],
			['label' => 'Docs', 'url' => 'https://example.org'],
		]));

		$this->assertSame([['Overview', 'page', 302, 'overview', null], ['Docs', 'url', null, 'item_2', null]], self::describeMenu($inserted));
	}

	public static function newPageTargetsOutsideTheApplication(): array {
		return [
			'page of another application' => [302],
			'page id that does not exist' => [9999],
		];
	}

	/**
	 * @dataProvider newPageTargetsOutsideTheApplication
	 */
	public function testANewPageTargetOutsideTheApplicationIsRefusedEvenWithABrokenStoredOne(int $pageId): void {
		// the stored item points at a page that is gone; that does not let other pages in
		$context = $this->contextWithMenu(7, [
			['targetType' => 'page', 'targetId' => 303, 'url' => null, 'technicalName' => 'gone', 'broken' => true],
		]);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->never())->method('deleteAllByContextId');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('a page of its own application');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Gone', 'targetType' => 'page', 'targetId' => 303],
			['label' => 'Elsewhere', 'targetType' => 'page', 'targetId' => $pageId],
		]));
	}

	public function testUpdateRefusesAPageItemWithoutAnId(): void {
		$context = $this->contextWithMenu(7, []);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->never())->method('deleteAllByContextId');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('a page of its own application');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Dashboard', 'targetType' => 'page'],
		], allowNewStartPage: true));
	}

	public function testUpdateAddsNoDashboardItem(): void {
		$context = $this->contextWithMenu(7, []);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'url' => 'https://example.org'],
		]));

		$this->assertSame([['Docs', 'url', null, 'item_1', null]], self::describeMenu($inserted));
	}

	public function testAGivenMenuNameTakenByAConcurrentSaveIsRefused(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->once())->method('insert')->willThrowException(DbErrorStub::uniqueViolation());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('already used');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'url' => 'https://example.org', 'technicalName' => 'docs'],
		]));
	}

	public function testMenuItemsKeepTheirIconAndDescription(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'url' => 'https://example.org', 'icon' => 'book', 'description' => 'How it works'],
		]));

		$this->assertSame([['book', 'How it works']], array_map(static fn (MenuItem $item): array => [$item->getIcon(), $item->getDescription()], $inserted));
	}

	private function buildPageContent(int $id, int $pageId, int $nodeRelId, int $order): PageContent {
		$pageContent = new PageContent();
		$pageContent->id = $id;
		$pageContent->setPageId($pageId);
		$pageContent->setNodeRelId($nodeRelId);
		$pageContent->setOrder($order);

		return $pageContent;
	}

	/**
	 * Mocks the inserts of an import and returns what they write.
	 *
	 * @return \ArrayObject<string, list<mixed>> pages, relations and widgets as inserted
	 */
	private function recordImport(): \ArrayObject {
		$record = new \ArrayObject(['pages' => [], 'relations' => [], 'widgets' => []]);
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 8;
			return $context;
		});
		$this->contextNodeRelMapper->method('insert')->willReturnCallback(static function (ContextNodeRelation $relation) use ($record): ContextNodeRelation {
			$relation->id = 501 + count($record['relations']);
			$record['relations'] = [...$record['relations'], $relation];
			return $relation;
		});
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use ($record): Page {
			$page->id = 600 + count($record['pages']);
			$record['pages'] = [...$record['pages'], $page];
			return $page;
		});
		$this->pageMapper->method('insertWithDefaultName')->willReturnCallback(static function (Page $page) use ($record): Page {
			$page->id = 600 + count($record['pages']);
			$record['pages'] = [...$record['pages'], $page];
			return $page;
		});
		$this->widgetService->method('insert')->willReturnCallback(static function (...$args) use ($record): \OCA\Tables\Db\Widget {
			$record['widgets'] = [...$record['widgets'], $args];
			return new \OCA\Tables\Db\Widget();
		});
		return $record;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function dataWidget(int $gridY, mixed $source): array {
		return ['type' => 'data', 'title' => null, 'gridX' => 0, 'gridY' => $gridY, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => [], 'source' => $source];
	}

	public function testAnImportedWidgetSourceKeepsOnlyTheTableBeingImported(): void {
		$exported = [
			'name' => 'Projects',
			'nodes' => [
				['id' => 101, 'node_id' => 5, 'node_type' => 0, 'permissions' => 1],
				['id' => 102, 'node_id' => 6, 'node_type' => 0, 'permissions' => 1],
			],
			'pages' => [[
				'id' => 301,
				'page_type' => Page::TYPE_GRID,
				'technical_name' => 'overview',
				'content' => [],
				'widgets' => [self::dataWidget(0, ['nodeRelId' => 101]), self::dataWidget(5, ['nodeRelId' => 102])],
			]],
		];
		$record = $this->recordImport();
		$table = new Table();
		$table->id = 78;
		$table->setOwnership('user-1');

		// the migrator imports the application once per table it holds; this is table 6
		$this->service->importContext($table, $exported, 6);

		$relationOfTable6 = $record['relations'][1];
		self::assertSame([null, $relationOfTable6->getId()], array_column($record['widgets'], 5));
	}

	public function testABrokenExportFileSkipsTheWidgetInsteadOfFailing(): void {
		$record = $this->recordImport();
		$table = new Table();
		$table->id = 77;
		$table->setOwnership('user-1');

		$this->service->importContext($table, [
			'name' => 'Projects',
			'nodes' => [['id' => [101], 'node_id' => 5, 'node_type' => 0, 'permissions' => 1], ['id' => 102, 'node_id' => 5, 'node_type' => 0, 'permissions' => 1]],
			'pages' => [[
				'id' => 301,
				'page_type' => Page::TYPE_GRID,
				'technical_name' => 'overview',
				'title' => ['Overview'],
				'description' => str_repeat('a', 2001),
				'content' => [['node_rel_id' => [102], 'order' => 10]],
				'widgets' => [
					self::dataWidget(0, ['nodeRelId' => [102]]),
					self::dataWidget(5, 'a source'),
					self::dataWidget(10, ['nodeRelId' => '102']),
					['type' => 'text', 'title' => null, 'description' => str_repeat('a', 2001), 'gridX' => 0, 'gridY' => 15, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => [], 'source' => null],
					self::dataWidget(20, ['nodeRelId' => 102]),
				],
			]],
		], 5);

		self::assertSame([null, null], [$record['pages'][0]->getTitle(), $record['pages'][0]->getDescription()]);
		self::assertCount(1, $record['widgets']);
		self::assertSame([20, 502], [$record['widgets'][0][3]['gridY'], $record['widgets'][0][5]]);
	}

	public function testImportedPageTextsAreTrimmed(): void {
		$record = $this->recordImport();
		$table = new Table();
		$table->id = 77;
		$table->setOwnership('user-1');

		$this->service->importContext($table, [
			'name' => 'Projects',
			'nodes' => [['id' => 101, 'node_id' => 5, 'node_type' => 0, 'permissions' => 1]],
			'pages' => [
				['id' => 301, 'page_type' => Page::TYPE_GRID, 'technical_name' => 'one', 'title' => '  Overview ', 'description' => ' ', 'content' => []],
				['id' => 302, 'page_type' => Page::TYPE_GRID, 'technical_name' => 'two', 'title' => str_repeat('a', 201), 'description' => str_repeat('a', 2000), 'content' => []],
			],
		], 5);

		self::assertSame(['Overview', null], [$record['pages'][0]->getTitle(), $record['pages'][0]->getDescription()]);
		self::assertSame([null, str_repeat('a', 2000)], [$record['pages'][1]->getTitle(), $record['pages'][1]->getDescription()]);
	}

	public function testAnImportedPageKeepsAtMostTheMaximumNumberOfWidgets(): void {
		$record = $this->recordImport();
		$table = new Table();
		$table->id = 77;
		$table->setOwnership('user-1');
		$widgets = [];
		for ($i = 0; $i < GridWidgetTypes::MAX_WIDGETS_PER_PAGE + 5; $i++) {
			$widgets[] = ['type' => 'text', 'title' => null, 'gridX' => 0, 'gridY' => $i * 2, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => [], 'source' => null];
		}

		$this->service->importContext($table, [
			'name' => 'Projects',
			'nodes' => [['id' => 101, 'node_id' => 5, 'node_type' => 0, 'permissions' => 1]],
			'pages' => [['id' => 301, 'page_type' => Page::TYPE_GRID, 'technical_name' => 'one', 'content' => [], 'widgets' => $widgets]],
		], 5);

		self::assertCount(GridWidgetTypes::MAX_WIDGETS_PER_PAGE, $record['widgets']);
	}

	public function testAMenuItemIsBrokenForAReaderWhoCannotOpenItsTarget(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([101 => ['id' => 101, 'node_id' => 11, 'node_type' => 0, 'permissions' => 1]]);
		$context->setMenuItems([
			['id' => 1, 'targetType' => 'table', 'targetId' => 11, 'broken' => false],
			['id' => 2, 'targetType' => 'table', 'targetId' => 5, 'broken' => false],
			['id' => 3, 'targetType' => 'view', 'targetId' => 6, 'broken' => false],
			['id' => 4, 'targetType' => 'url', 'targetId' => null, 'broken' => false],
			['id' => 5, 'targetType' => 'table', 'targetId' => 9, 'broken' => true],
		]);
		$this->contextMapper->method('findById')->willReturnCallback(static fn (): Context => clone $context);
		$this->contextMapper->method('findAll')->willReturnCallback(static fn (): array => [clone $context]);
		$this->permissionsService->method('canAccessNodeById')->willReturnCallback(
			static fn (int $nodeType, int $nodeId, string $userId): bool => $userId === 'owner' || ($nodeType === 1 && $nodeId === 6),
		);

		self::assertSame([false, false, false, false, true], array_column($this->service->findById(7, 'owner')->getMenuItems(), 'broken'));
		self::assertSame([false, true, false, false, true], array_column($this->service->findById(7, 'sharee')->getMenuItems(), 'broken'));
		self::assertSame([false, true, false, false, true], array_column($this->service->findAll('sharee')[0]->getMenuItems(), 'broken'));
	}

	public function testDeletingAnApplicationRetriesATransientDatabaseError(): void {
		$context = new Context();
		$context->id = 7;
		$this->contextMapper->method('findById')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->willReturn([]);
		$deadlock = \OC\DB\Exceptions\DbalException::wrap(new \Doctrine\DBAL\Exception\DeadlockException(
			\Doctrine\DBAL\Driver\PDO\Exception::new(new \PDOException('deadlock')),
			null,
		));
		$attempts = 0;
		$this->shareService->method('deleteAllForContext')->willReturnCallback(static function () use (&$attempts, $deadlock): void {
			if (++$attempts === 1) {
				throw $deadlock;
			}
		});
		$this->contextMapper->expects($this->exactly(2))->method('delete');
		$this->dbConnection->expects($this->once())->method('rollBack');

		$this->service->delete(7, 'alice');

		self::assertSame(2, $attempts);
	}

	public function testAMenuOfMoreThanTheMaximumNumberOfItemsIsRefused(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([]);
		$context->setPages([]);
		$context->setMenuItems([]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->never())->method('deleteAllByContextId');
		$items = array_fill(0, MenuItem::MAX_ITEMS_PER_APPLICATION + 1, ['label' => 'Docs', 'url' => 'https://example.org']);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('at most 200 items');
		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray($items));
	}

	public function testAMenuOfTheMaximumNumberOfItemsIsSaved(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([]);
		$context->setPages([]);
		$context->setMenuItems([]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->menuItemMapper->expects($this->exactly(MenuItem::MAX_ITEMS_PER_APPLICATION))->method('insertWithDefaultName');
		$items = array_fill(0, MenuItem::MAX_ITEMS_PER_APPLICATION, ['label' => 'Docs', 'url' => 'https://example.org']);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray($items));
	}
}
