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
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Db\WidgetSourceMapper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\MenuItemInput;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Runs against the database: a grid page with widgets is exported and imported again,
 * and removing or deleting its parts cleans up the widget rows.
 */
class ContextPagesRoundTripTest extends DatabaseTestCase {
	private const OWNER = 'context-pages-round-trip-owner';

	private ContextService $service;
	private ContextMapper $contextMapper;
	private ContextNodeRelationMapper $nodeRelMapper;
	private PageMapper $pageMapper;
	private WidgetService $widgetService;
	private PermissionsService&MockObject $permissionsService;
	private TableMapper&MockObject $tableMapper;
	/** @var int[] */
	private array $contextIds = [];

	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$userHelper = $this->createMock(UserHelper::class);
		$userHelper->method('getGroupIdsForUser')->willReturn([]);
		$this->contextMapper = new ContextMapper($this->connectionAdapter, $userHelper, $this->createMock(GroupHelper::class), new MenuItemMapper($this->connectionAdapter));
		$this->nodeRelMapper = new ContextNodeRelationMapper($this->connectionAdapter);
		$this->pageMapper = new PageMapper($this->connectionAdapter);
		$this->widgetService = new WidgetService(
			new WidgetMapper($this->connectionAdapter),
			new WidgetSourceMapper($this->connectionAdapter),
		);
		$this->service = new ContextService(
			$this->contextMapper,
			$this->nodeRelMapper,
			$this->pageMapper,
			new PageContentMapper($this->connectionAdapter),
			$this->createMock(LoggerInterface::class),
			$this->permissionsService = $this->createMock(PermissionsService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IEventDispatcher::class),
			$this->connectionAdapter,
			$this->createMock(ShareService::class),
			false,
			$this->createMock(INavigationManager::class),
			$this->createMock(IURLGenerator::class),
			$this->tableMapper = $this->createMock(TableMapper::class),
			$this->createMock(ViewMapper::class),
			$this->widgetService,
			new GridWidgetTypes($l10n),
			new MenuItemMapper($this->connectionAdapter),
			$l10n,
		);
	}

	protected function tearDown(): void {
		foreach ($this->contextIds as $contextId) {
			try {
				$this->service->delete($contextId, self::OWNER);
			} catch (\Throwable) {
			}
		}
		parent::tearDown();
	}

	public function testExportedWidgetsAreImportedWithTheirNode(): void {
		[$context, $relation, $gridPageId] = $this->createContextWithGridPage();
		$table = new Table();
		$table->id = 4242;
		$table->setOwnership(self::OWNER);

		$exported = json_decode(json_encode(array_merge(
			$this->contextMapper->findById($context->getId(), self::OWNER)->jsonSerialize(),
			['pages' => $this->service->getPagesWithWidgets($this->contextMapper->findById($context->getId(), self::OWNER))],
		)), true);
		$exported['nodes'][$relation->getId()]['node_id'] = 4242;

		$imported = $this->service->importContext($table, $exported, 4242);
		$this->assertNotNull($imported);
		$this->contextIds[] = $imported->getId();

		$importedContext = $this->contextMapper->findById($imported->getId(), self::OWNER);
		$importedRelationId = array_key_first($importedContext->getNodes());
		$importedPages = $this->service->getPagesWithWidgets($importedContext);
		$gridPages = array_values(array_filter($importedPages, static fn (array $page): bool => $page['page_type'] === Page::TYPE_GRID));
		$this->assertCount(1, $gridPages);
		$this->assertSame(['overview', 'Overview', 'star', 'At a glance'], [$gridPages[0]['technical_name'], $gridPages[0]['title'], $gridPages[0]['icon'], $gridPages[0]['description']]);
		$startPages = array_values(array_filter($importedPages, static fn (array $page): bool => $page['page_type'] === Page::TYPE_STARTPAGE));
		$this->assertSame('dashboard', $startPages[0]['technical_name']);

		$original = $this->service->getPagesWithWidgets($this->contextMapper->findById($context->getId(), self::OWNER))[$gridPageId]['widgets'];
		$withoutIds = static fn (array $widgets): array => array_map(static function (array $widget): array {
			unset($widget['id']);
			return $widget;
		}, $widgets);
		$expected = $withoutIds($original);
		$expected[1]['source'] = ['nodeRelId' => $importedRelationId];
		$this->assertNotSame($relation->getId(), $importedRelationId);
		$this->assertSame(json_encode($expected), json_encode($withoutIds($gridPages[0]['widgets'])));
	}

	public function testAnUnknownIconIsDroppedOnImport(): void {
		[$context, $relation, $gridPageId] = $this->createContextWithGridPage();
		$table = new Table();
		$table->id = 4243;
		$table->setOwnership(self::OWNER);
		$exported = json_decode(json_encode(array_merge(
			$this->contextMapper->findById($context->getId(), self::OWNER)->jsonSerialize(),
			['pages' => $this->service->getPagesWithWidgets($this->contextMapper->findById($context->getId(), self::OWNER))],
		)), true);
		$exported['nodes'][$relation->getId()]['node_id'] = 4243;
		$exported['pages'][$gridPageId]['icon'] = 'no-such-icon';
		$exported['pages'][$gridPageId]['widgets'][0]['icon'] = '../star';

		$imported = $this->service->importContext($table, $exported, 4243);
		$this->assertNotNull($imported);
		$this->contextIds[] = $imported->getId();

		$importedPages = $this->service->getPagesWithWidgets($this->contextMapper->findById($imported->getId(), self::OWNER));
		$gridPages = array_values(array_filter($importedPages, static fn (array $page): bool => $page['page_type'] === Page::TYPE_GRID));
		$this->assertNull($gridPages[0]['icon']);
		$this->assertNull($gridPages[0]['widgets'][0]['icon']);
		$this->assertSame('Where to start', $gridPages[0]['widgets'][0]['description']);
	}

	public function testTheMenuIsImportedWithItsPagesAndNesting(): void {
		[$context, $relation, $gridPageId] = $this->createContextWithGridPage();
		$this->service->update($context->getId(), self::OWNER, null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Overview', 'targetType' => 'page', 'targetId' => $gridPageId, 'technicalName' => 'overview'],
			['label' => 'Docs', 'url' => 'https://example.org', 'icon' => 'book', 'description' => 'How it works', 'parentIndex' => 0],
		]));
		$table = new Table();
		$table->id = 4244;
		$table->setOwnership(self::OWNER);
		$exported = json_decode(json_encode(array_merge(
			$this->contextMapper->findById($context->getId(), self::OWNER)->jsonSerialize(),
			['pages' => $this->service->getPagesWithWidgets($this->contextMapper->findById($context->getId(), self::OWNER))],
		)), true);
		$exported['nodes'][$relation->getId()]['node_id'] = 4244;
		$this->assertStringStartsWith('item_', $exported['menuItems'][1]['technicalName']);

		$imported = $this->service->importContext($table, $exported, 4244);
		$this->assertNotNull($imported);
		$this->contextIds[] = $imported->getId();

		$importedContext = $this->contextMapper->findById($imported->getId(), self::OWNER);
		$menu = $importedContext->getMenuItems();
		$importedGridPageId = array_key_first(array_filter($importedContext->getPages(), static fn (array $page): bool => $page['page_type'] === Page::TYPE_GRID));
		$this->assertSame(
			[['overview', 'page', $importedGridPageId, null, null], ['item_' . $menu[1]['id'], 'url', null, 'book', 'How it works']],
			array_map(static fn (array $item): array => [$item['technicalName'], $item['targetType'], $item['targetId'], $item['icon'], $item['description']], $menu),
		);
		$this->assertSame([null, $menu[0]['id']], array_column($menu, 'parentId'));
		$this->assertNotSame($gridPageId, $importedGridPageId);
	}

	public function testRemovingTheNodeKeepsTheWidgetAndDeletingTheContextRemovesIt(): void {
		[$context, $relation, $gridPageId] = $this->createContextWithGridPage();

		$this->service->removeNodeFromContextAndPages($relation->getId());

		$widgets = $this->widgetService->findByPageIds([$gridPageId])[$gridPageId];
		$this->assertCount(2, $widgets);
		$this->assertNull($widgets[1]['source']);
		$this->assertSame('data', $widgets[1]['type']);

		$this->service->delete($context->getId(), self::OWNER);

		$this->assertSame([], $this->widgetService->findByPageIds([$gridPageId]));
		$this->assertSame([], $this->pageMapper->getPageIdsForContext($context->getId()));
	}

	/**
	 * @return array{0: Context, 1: ContextNodeRelation, 2: int}
	 */
	private function createContextWithGridPage(): array {
		$context = $this->service->create('Projects', 'tables', '', [], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $context->getId();
		$relation = $this->service->addNodeToContext($context, 4141, Application::NODE_TYPE_TABLE, Application::PERMISSION_READ);
		$this->service->addNodeRelToPage($relation);

		$page = new Page();
		$page->setContextId($context->getId());
		$page->setPageType(Page::TYPE_GRID);
		$page->setTechnicalName('overview');
		$page->setTitle('Overview');
		$page->setIcon('star');
		$page->setDescription('At a glance');
		$page = $this->pageMapper->insert($page);

		$this->widgetService->insert($page->getId(), 'header', 'Welcome', ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2], ['heading' => 'Projects', 'subheading' => '', 'textAlign' => 'center', 'backgroundColor' => '#123456', 'textColor' => ''], null, 'home', 'Where to start');
		$this->widgetService->insert($page->getId(), 'data', null, ['gridX' => 0, 'gridY' => 2, 'gridWidth' => 12, 'gridHeight' => 5], [], $relation->getId());

		return [$context, $relation, $page->getId()];
	}

	/**
	 * The frontend sends a table both as a node and as a menu target.
	 */
	public function testATableInBothTheNodesAndTheMenuIsAddedOnceOnCreateAndUpdate(): void {
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->tableMapper->method('findMany')->willReturnCallback(static fn (array $ids): array => array_combine($ids, array_map(static fn (): Table => new Table(), $ids)));
		$node = static fn (int $id): array => ['id' => $id, 'type' => Application::NODE_TYPE_TABLE, 'permissions' => Application::PERMISSION_READ];
		$item = static fn (int $id): array => ['label' => 'Table ' . $id, 'targetType' => 'table', 'targetId' => $id];

		$created = $this->service->create('Nodes and menu', 'tables', '', [$node(990501)], self::OWNER, Application::OWNER_TYPE_USER,
			MenuItemInput::createListFromInputArray([$item(990501)], allowNewStartPage: true));
		$this->contextIds[] = $created->getId();
		$this->assertSame([990501], $this->storedNodeIds($created->getId()));

		$this->service->update($created->getId(), self::OWNER, null, null, null, [$node(990501), $node(990502)],
			MenuItemInput::createListFromInputArray([$item(990501), $item(990502)]));
		$this->assertSame([990501, 990502], $this->storedNodeIds($created->getId()));
	}

	/**
	 * The node ids of an application, once for every relation, and the start page holds
	 * each relation once.
	 *
	 * @return list<int>
	 */
	private function storedNodeIds(int $contextId): array {
		$context = $this->contextMapper->findById($contextId, self::OWNER);
		$nodeIds = array_values(array_map(static fn (array $node): int => $node['node_id'], $context->getNodes()));
		sort($nodeIds);
		$startPage = array_values(array_filter($context->getPages(), static fn (array $page): bool => $page['page_type'] === Page::TYPE_STARTPAGE))[0];
		$this->assertCount(count($nodeIds), $startPage['content']);
		return $nodeIds;
	}

	/**
	 * The responses of creating and updating an application keep the shape they had
	 * before menus: a new application lists its nodes as relations and its start page
	 * contents by id, and an update returns a node it added as its relation.
	 */
	public function testCreateAndUpdateResponsesKeepTheirNodeShape(): void {
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$node = static fn (int $id, int $permissions): array => ['id' => $id, 'type' => Application::NODE_TYPE_TABLE, 'permissions' => $permissions];

		$created = $this->service->create('Shape', 'tables', '', [$node(990601, 1), $node(990602, 3)], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $id = $created->getId();
		$json = json_decode(json_encode($created), true);
		[$first, $second] = $json['nodes'];
		$this->assertSame(array_keys($json['nodes']), [0, 1]);
		$this->assertSame(['id' => $first['id'], 'contextId' => $id, 'nodeId' => 990601, 'nodeType' => 0, 'permissions' => 1], $first);
		$this->assertSame(['id' => $second['id'], 'contextId' => $id, 'nodeId' => 990602, 'nodeType' => 0, 'permissions' => 3], $second);
		$startPage = array_values($json['pages'])[0];
		$this->assertSame(['id', 'contextId', 'pageType', 'technicalName', 'title', 'icon', 'description', 'content'], array_keys($startPage));
		$contentIds = array_keys($startPage['content']);
		$this->assertSame([
			['id' => $contentIds[0], 'nodeRelId' => $first['id'], 'order' => 10],
			['id' => $contentIds[1], 'nodeRelId' => $second['id'], 'order' => 20],
		], array_values($startPage['content']));

		$updated = json_decode(json_encode($this->service->update($id, self::OWNER, null, null, null, [$node(990602, 1), $node(990603, 1)])), true);
		$this->assertSame([$second['id']], array_values(array_diff(array_keys($updated['nodes']), [max(array_keys($updated['nodes']))])));
		$added = $updated['nodes'][max(array_keys($updated['nodes']))];
		$this->assertSame(['id', 'contextId', 'nodeId', 'nodeType', 'permissions'], array_keys($added));
		$this->assertSame(['id', 'node_id', 'node_type', 'permissions'], array_keys($updated['nodes'][$second['id']]));
		$addedContent = array_values(array_filter(array_values($updated['pages'])[0]['content'], static fn (array $content): bool => isset($content['nodeRelId'])));
		$this->assertSame(['id', 'pageId', 'nodeRelId', 'order'], array_keys($addedContent[0]));
		$this->assertSame($added['id'], $addedContent[0]['nodeRelId']);
	}

	public function testASchemeRoundTripKeepsAChildUnderAnAutomaticallyNamedParent(): void {
		$source = $this->service->create('Source', 'tables', '', [], self::OWNER, Application::OWNER_TYPE_USER, []);
		$this->contextIds[] = $source->getId();
		$this->service->update($source->getId(), self::OWNER, null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Help', 'url' => 'https://example.org/help'],
			['label' => 'Docs', 'url' => 'https://example.org/docs', 'parentIndex' => 0],
		]));
		$scheme = json_decode(json_encode($this->service->getScheme($source->getId(), self::OWNER)), true);
		$this->assertStringStartsWith('item_', $scheme['menuItems'][0]['technicalName']);
		$this->assertSame($scheme['menuItems'][0]['technicalName'], $scheme['menuItems'][1]['parent']);

		$target = $this->service->create('Target', 'tables', '', [], self::OWNER, Application::OWNER_TYPE_USER, []);
		$this->contextIds[] = $target->getId();
		// the preview lists all tables through the session user, which a test run has not
		$tableService = $this->createMock(TableService::class);
		$tableService->method('findAll')->willReturn([]);
		$container = \OC::$server->getAppContainerForService(TableService::class) ?? \OC::$server;
		$realTableService = \OCP\Server::get(TableService::class);
		$container->registerService(TableService::class, static fn () => $tableService);
		try {
			$preview = $this->service->compareSchemeChanges($target->getId(), ['menuItems' => [...$scheme['menuItems'], ['label' => 'Gone', 'targetType' => 'view', 'targetUuid' => 'no-such-view']]], self::OWNER);
		} finally {
			$container->registerService(TableService::class, static fn () => $realTableService);
		}
		$this->service->importScheme($target->getId(), 'Target', 'tables', '', [], ['addTables' => [], 'modifyTables' => []], self::OWNER, $scheme['menuItems']);

		$menu = $this->contextMapper->findById($target->getId(), self::OWNER)->getMenuItems();
		$this->assertSame(['Help', 'Docs'], array_column($menu, 'label'));
		$this->assertSame([null, $menu[0]['id']], array_column($menu, 'parentId'));
		$this->assertSame(['Help', 'Docs'], array_column($preview['menuItems']['to'], 'label'));
		$this->assertSame([], $preview['menuItems']['from']);
		$this->assertSame([['index' => 2, 'label' => 'Gone']], array_map(static fn (array $skip): array => ['index' => $skip['index'], 'label' => $skip['label']], $preview['menuItems']['skipped']));
	}
}
