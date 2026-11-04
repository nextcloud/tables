<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
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
	private MockObject $userManager;
	private MockObject $auditLogService;
	private MockObject $widgetService;
	private MockObject $dbConnection;
	private MockObject $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$permissionsService = $this->createMock(PermissionsService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);
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
			$this->logger,
			$permissionsService,
			$this->userManager,
			$this->auditLogService,
			$this->dbConnection,
			$shareService,
			false,
			$navigationManager,
			$urlGenerator,
			$this->createMock(TableMapper::class),
			$this->createMock(ViewMapper::class),
			$this->widgetService,
			new GridWidgetTypes($l10n),
			$this->createMock(MenuItemMapper::class),
			$this->createMock(MenuService::class),
		);
	}

	public function testTransferLogsPreviousAndNewOwner(): void {
		$context = new Context();
		$context->setId(7);
		$context->setOwnerId('alice');
		$context->setOwnerType(Application::OWNER_TYPE_USER);
		$this->contextMapper->method('findById')->with(7)->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->userManager->method('userExists')->with('bob')->willReturn(true);

		$this->auditLogService->expects($this->once())
			->method('log')
			->with(
				Operation::ContextTransferred,
				'Tables application with ID %s was transferred to user %s',
				['contextId' => 7, 'toUserId' => 'bob', 'fromUserId' => 'alice'],
			);

		$this->service->transfer(7, 'bob', Application::OWNER_TYPE_USER);
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
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextUpdated, 'Tables application with ID %s was updated', [
				'contextId' => 7,
				'addedNodes' => [],
				'removedNodes' => [],
				'updatedNodes' => [],
			]);

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

	public function testUpdateLogsRemovedNodes(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([
			101 => ['id' => 101, 'node_type' => 0, 'node_id' => 11, 'permissions' => 1],
		]);
		$context->setPages([]);
		$this->contextMapper->method('findById')->with(7, 'user-1')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$nodeRelation = new ContextNodeRelation();
		$nodeRelation->id = 101;
		$this->contextNodeRelMapper->method('findById')->with(101)->willReturn($nodeRelation);
		$this->contextNodeRelMapper->method('delete')->willReturnArgument(0);
		$this->pageContentMapper->method('findByNodeRelation')->willReturn([]);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextUpdated, 'Tables application with ID %s was updated', [
				'contextId' => 7,
				'addedNodes' => [],
				'removedNodes' => [['nodeType' => 0, 'nodeId' => 11]],
				'updatedNodes' => [],
			]);

		$this->service->update(7, 'user-1', null, null, null, []);
	}

	public function testUpdateLogsNodePermissionChanges(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([
			101 => ['id' => 101, 'node_type' => 0, 'node_id' => 11, 'permissions' => 1],
		]);
		$context->setPages([]);
		$this->contextMapper->method('findById')->with(7, 'user-1')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$nodeRelation = new ContextNodeRelation();
		$nodeRelation->id = 101;
		$nodeRelation->setNodeType(0);
		$nodeRelation->setNodeId(11);
		$nodeRelation->setPermissions(1);
		$this->contextNodeRelMapper->method('findById')->with(101)->willReturn($nodeRelation);
		$this->contextNodeRelMapper->expects($this->once())->method('update')->with($nodeRelation)->willReturnArgument(0);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextUpdated, 'Tables application with ID %s was updated', [
				'contextId' => 7,
				'addedNodes' => [],
				'removedNodes' => [],
				'updatedNodes' => [['nodeType' => 0, 'nodeId' => 11, 'previousPermissions' => 1, 'permissions' => 3]],
			]);

		$this->service->update(7, 'user-1', null, null, null, [
			['id' => 11, 'type' => 0, 'permissions' => 3],
		]);

		self::assertSame(3, $nodeRelation->getPermissions());
	}

	public function testCreateLogsCriticalAction(): void {
		$this->contextMapper->method('insert')->willReturnCallback(function (Context $context): Context {
			$context->id = 9;
			return $context;
		});
		$this->pageMapper->method('insert')->willReturnCallback(function (Page $page): Page {
			$page->id = 1;
			return $page;
		});
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextCreated, 'Tables application with ID %s was created for user %s', [
				'contextId' => 9,
				'ownerId' => 'alice',
				'nodes' => [],
			]);

		$this->service->create('Projects', 'icon', '', [], 'alice', Application::OWNER_TYPE_USER);
	}

	public function testDeleteLogsCriticalAction(): void {
		$context = new Context();
		$context->id = 9;
		$context->setOwnerId('alice');
		$this->contextMapper->method('findById')->with(9, 'alice')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->willReturn([]);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextDeleted, 'Tables application with ID %s was deleted', [
				'contextId' => 9,
				'ownerId' => 'alice',
			]);

		$this->service->delete(9, 'alice');
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

	public function testAFailedNodeRemovalRollsBackTheDeletedSources(): void {
		$this->contextNodeRelMapper->method('findById')->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('gone'));
		$this->widgetService->expects($this->once())->method('deleteSourcesByNodeRelIds')->with([101]);
		$this->dbConnection->expects($this->once())->method('rollBack');
		$this->dbConnection->expects($this->never())->method('commit');

		$this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
		$this->service->removeNodeFromContextAndPages(101);
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
				'title' => '  Overview ',
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

	public static function importedPages(): array {
		$start = Page::TYPE_STARTPAGE;
		$grid = Page::TYPE_GRID;
		return [
			'free names are kept, the rest get the default' => [
				[
					['id' => 1, 'page_type' => $start],
					['id' => 2, 'page_type' => $grid, 'technical_name' => 'sales'],
					['id' => 3, 'page_type' => $grid, 'technical_name' => 'sales'],
					['id' => 4, 'page_type' => $grid, 'technical_name' => 'Bad Name'],
					['id' => 5, 'page_type' => $grid],
					['id' => 6, 'page_type' => $grid, 'technical_name' => 'page_2'],
				],
				[80 => [$start, 'dashboard'], 81 => [$grid, 'sales'], 82 => [$grid, 'page_82'], 83 => [$grid, 'page_83'], 84 => [$grid, 'page_84'], 85 => [$grid, 'page_85']],
			],
			'the start page leaves dashboard to the page exported with it' => [
				[['id' => 1, 'page_type' => $start], ['id' => 2, 'page_type' => $grid, 'technical_name' => 'dashboard']],
				[80 => [$start, 'page_80'], 81 => [$grid, 'dashboard']],
			],
			'only one start page and grid pages are imported' => [
				[
					['id' => 1, 'pageType' => $start, 'technicalName' => 'home'],
					['id' => 2, 'page_type' => $start],
					['id' => 3, 'page_type' => 'chart'],
					['id' => 4],
					'not a page',
					['id' => 5, 'page_type' => $grid],
				],
				[80 => [$start, 'home'], 81 => [$grid, 'page_81']],
			],
		];
	}

	/**
	 * @dataProvider importedPages
	 * @param array<int, array{0: string, 1: string}> $expected type and technical name by page id
	 */
	public function testImportedPages(array $pages, array $expected): void {
		$table = new Table();
		$table->id = 40;
		$table->setOwnership('user-1');
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 8;
			return $context;
		});
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$inserted = [];
		$pageId = 80;
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use (&$inserted, &$pageId): Page {
			$page->id = $pageId++;
			$inserted[$page->getId()] = [$page->getPageType(), $page->getTechnicalName()];
			return $page;
		});
		$this->pageMapper->method('insertWithDefaultName')->willReturnCallback(static function (Page $page) use (&$inserted, &$pageId): Page {
			$page->id = $pageId++;
			$inserted[$page->getId()] = [$page->getPageType(), 'page_' . $page->getId()];
			return $page;
		});

		$this->service->importContext($table, ['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => $pages], 4);

		self::assertSame($expected, $inserted);
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

	public function testImportingSchemePagesKeepsWhatCanBeUsedAndSkipsTheRestWithAReason(): void {
		$context = new Context();
		$context->id = 7;
		$context->setName('Intake portal');
		$context->setOwnerId('user-1');
		$context->setOwnerType(Application::OWNER_TYPE_USER);
		$context->setNodes([]);
		$context->setPages([
			301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'technical_name' => 'dashboard', 'content' => []],
			302 => ['id' => 302, 'page_type' => Page::TYPE_GRID, 'technical_name' => 'overview', 'content' => []],
		]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->pageMapper->method('findById')->willReturnCallback(static function (int $id): Page {
			$page = new Page();
			$page->id = $id;
			$page->resetUpdatedFields();
			return $page;
		});
		$updated = [];
		$this->pageMapper->method('update')->willReturnCallback(static function (Page $page) use (&$updated): Page {
			$updated[] = [$page->getId(), $page->getTitle(), $page->getIcon()];
			return $page;
		});
		$inserted = [];
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use (&$inserted): Page {
			$page->id = 400 + count($inserted);
			$inserted[] = [$page->getTechnicalName(), $page->getTitle(), $page->getPageType()];
			return $page;
		});
		$this->pageMapper->method('insertWithDefaultName')->willReturnCallback(static function (Page $page) use (&$inserted): Page {
			$page->id = 400 + count($inserted);
			$inserted[] = ['page_' . $page->getId(), $page->getTitle(), $page->getPageType()];
			return $page;
		});
		$synced = [];
		$this->widgetService->method('syncPage')->willReturnCallback(static function (int $pageId, array $widgets) use (&$synced): void {
			$synced[$pageId] = array_map(static fn (array $widget): array => [$widget['type'], $widget['title'], $widget['nodeRelId']], $widgets);
		});
		$warnings = [];
		$this->logger->method('warning')->willReturnCallback(static function (string $message) use (&$warnings): void {
			$warnings[] = $message;
		});
		$text = static fn (?string $title): array => ['type' => 'text', 'title' => $title, 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => ['text' => 'Hi']];

		$this->service->importScheme(7, 'Intake', 'star', '', [], ['addTables' => [], 'modifyTables' => []], 'user-1', null, [
			['page_type' => Page::TYPE_STARTPAGE, 'technical_name' => 'dashboard', 'title' => 'Home', 'icon' => 'star', 'widgets' => [$text('On the start page')]],
			['page_type' => Page::TYPE_STARTPAGE, 'title' => 'Second start'],
			['page_type' => Page::TYPE_GRID, 'technical_name' => 'overview', 'title' => 'Overview', 'widgets' => [
				$text('Kept'),
				['type' => 'data', 'title' => 'Without source', 'gridX' => 0, 'gridY' => 2, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => [], 'source' => null],
				['type' => 'data', 'title' => 'Old source', 'gridX' => 0, 'gridY' => 7, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => [], 'source' => ['nodeRelId' => 5]],
				['type' => 'chart', 'title' => 'Unknown type', 'gridX' => 0, 'gridY' => 12, 'gridWidth' => 12, 'gridHeight' => 5],
			]],
			['page_type' => Page::TYPE_GRID, 'technical_name' => 'overview', 'title' => 'Same name'],
			'not a page',
			['page_type' => Page::TYPE_GRID, 'technical_name' => 'reports', 'title' => 'Reports', 'widgets' => array_fill(0, GridWidgetTypes::MAX_WIDGETS_PER_PAGE + 1, $text(null))],
		]);

		$this->assertSame([[301, 'Home', 'star'], [302, 'Overview', null]], $updated);
		$this->assertSame([['page_400', 'Same name', Page::TYPE_GRID], ['reports', 'Reports', Page::TYPE_GRID]], $inserted);
		$this->assertSame([['text', 'Kept', null], ['data', 'Without source', null]], $synced[302]);
		$this->assertSame([], $synced[400]);
		$this->assertCount(GridWidgetTypes::MAX_WIDGETS_PER_PAGE, $synced[401]);
		$this->assertArrayNotHasKey(301, $synced);
		$this->assertSame([
			'Skipping widget 0 of page 0 of an imported scheme: only grid pages hold widgets',
			'Skipping page 1 of an imported scheme: an application has one start page',
			'Skipping widget 2 of page 2 of an imported scheme: its source names no table or view by uuid',
			'Skipping widget 3 of page 2 of an imported scheme: Unknown widget type "chart", expected one of: header, text, data',
			'Skipping page 4 of an imported scheme: its type is not startpage or grid',
			'Skipping widget 100 of page 5 of an imported scheme: a page holds at most 100 widgets',
		], $warnings);
	}
}
