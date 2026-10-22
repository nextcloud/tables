<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
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
	private MockObject $dbConnection;

	protected function setUp(): void {
		parent::setUp();

		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$permissionsService = $this->createMock(PermissionsService::class);
		$userManager = $this->createMock(IUserManager::class);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->widgetService = $this->createMock(WidgetService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$shareService = $this->createMock(ShareService::class);
		$navigationManager = $this->createMock(INavigationManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);

		$this->service = new ContextService(
			$this->contextMapper,
			$this->contextNodeRelMapper,
			$this->pageMapper,
			$this->pageContentMapper,
			$logger,
			$permissionsService,
			$userManager,
			$eventDispatcher,
			$this->dbConnection,
			$shareService,
			false,
			$navigationManager,
			$urlGenerator,
			$this->createMock(TableMapper::class),
			$this->createMock(ViewMapper::class),
			$this->widgetService,
			new GridWidgetTypes($l10n),
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
}
