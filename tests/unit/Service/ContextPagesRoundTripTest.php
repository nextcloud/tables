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
use OCA\Tables\Model\WidgetInput;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\MenuService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
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
		$this->contextMapper = new ContextMapper($this->connectionAdapter, $userHelper, $this->createMock(GroupHelper::class));
		$this->nodeRelMapper = new ContextNodeRelationMapper($this->connectionAdapter);
		$this->pageMapper = new PageMapper($this->connectionAdapter);
		$this->widgetService = new WidgetService(
			new WidgetMapper($this->connectionAdapter),
			new WidgetSourceMapper($this->connectionAdapter),
			$this->connectionAdapter,
		);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->service = new ContextService(
			$this->contextMapper,
			$this->nodeRelMapper,
			$this->pageMapper,
			new PageContentMapper($this->connectionAdapter),
			$this->createMock(LoggerInterface::class),
			$this->permissionsService,
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogServiceInterface::class),
			$this->connectionAdapter,
			$this->createMock(ShareService::class),
			false,
			$this->createMock(INavigationManager::class),
			$this->createMock(IURLGenerator::class),
			$this->tableMapper,
			$this->createMock(ViewMapper::class),
			$this->widgetService,
			new GridWidgetTypes($l10n),
			$this->createMock(MenuItemMapper::class),
			$this->createMock(MenuService::class),
		);
	}

	protected function tearDown(): void {
		// every application of the test owner, also one an import left behind when it failed
		foreach ($this->contextMapper->findAll(self::OWNER) as $context) {
			$this->service->delete($context->getId(), self::OWNER);
		}
		parent::tearDown();
	}

	public function testExportedWidgetsAreImportedWithTheirNode(): void {
		[$context, $relation, $gridPageId] = $this->createContextWithGridPage();

		$exported = json_decode(json_encode(array_merge(
			$this->contextMapper->findById($context->getId(), self::OWNER)->jsonSerialize(),
			['pages' => $this->service->getPagesWithWidgets($this->contextMapper->findById($context->getId(), self::OWNER))],
		)), true);
		$exported['nodes'][$relation->getId()]['node_id'] = 4242;

		$imported = $this->service->importContext($exported, self::OWNER, [4242 => 4242], []);
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
		$exported = json_decode(json_encode(array_merge(
			$this->contextMapper->findById($context->getId(), self::OWNER)->jsonSerialize(),
			['pages' => $this->service->getPagesWithWidgets($this->contextMapper->findById($context->getId(), self::OWNER))],
		)), true);
		$exported['nodes'][$relation->getId()]['node_id'] = 4243;
		$exported['pages'][$gridPageId]['icon'] = 'no-such-icon';
		$exported['pages'][$gridPageId]['widgets'][0]['icon'] = '../star';

		$imported = $this->service->importContext($exported, self::OWNER, [4243 => 4243], []);
		$this->assertNotNull($imported);
		$this->contextIds[] = $imported->getId();

		$importedPages = $this->service->getPagesWithWidgets($this->contextMapper->findById($imported->getId(), self::OWNER));
		$gridPages = array_values(array_filter($importedPages, static fn (array $page): bool => $page['page_type'] === Page::TYPE_GRID));
		$this->assertNull($gridPages[0]['icon']);
		$this->assertNull($gridPages[0]['widgets'][0]['icon']);
		$this->assertSame('Where to start', $gridPages[0]['widgets'][0]['description']);
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

	public function testAnApplicationOfTwoTablesAndAViewIsImportedWithEachNodeOnItsOwnTarget(): void {
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->tableMapper->method('findMany')->willReturnCallback(static fn (array $ids): array => array_combine($ids, array_map(static fn (): Table => new Table(), $ids)));
		$node = static fn (int $id, int $type): array => ['id' => $id, 'type' => $type, 'permissions' => Application::PERMISSION_READ];
		$context = $this->service->create('Two tables', 'tables', '', [
			$node(4151, Application::NODE_TYPE_TABLE),
			$node(4152, Application::NODE_TYPE_TABLE),
			$node(4153, Application::NODE_TYPE_VIEW),
		], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $context->getId();
		$stored = $this->contextMapper->findById($context->getId(), self::OWNER);
		$relationIds = array_column($stored->getNodes(), 'id', 'node_id');
		$page = new Page();
		$page->setContextId($context->getId());
		$page->setPageType(Page::TYPE_GRID);
		$page->setTechnicalName('overview');
		$page = $this->pageMapper->insert($page);
		foreach ([4151, 4152, 4153] as $y => $nodeId) {
			$this->widgetService->insertAll($page->getId(), [new WidgetInput(null, 'data', 'Node ' . $nodeId, null, null, 0, $y * 5, 12, 5, [], $relationIds[$nodeId])]);
		}
		$stored = $this->contextMapper->findById($context->getId(), self::OWNER);
		$exported = json_decode(json_encode(array_merge($stored->jsonSerialize(), ['pages' => $this->service->getPagesWithWidgets($stored)])), true);

		$imported = $this->service->importContext($exported, self::OWNER, [4151 => 5151, 4152 => 5152], [4153 => 5153]);
		$this->assertNotNull($imported);
		$this->contextIds[] = $imported->getId();

		$importedContext = $this->contextMapper->findById($imported->getId(), self::OWNER);
		$nodes = array_values($importedContext->getNodes());
		$this->assertSame(
			[[5151, Application::NODE_TYPE_TABLE], [5152, Application::NODE_TYPE_TABLE], [5153, Application::NODE_TYPE_VIEW]],
			array_map(static fn (array $node): array => [$node['node_id'], $node['node_type']], $nodes),
		);
		$importedRelationIds = array_column($nodes, 'id', 'node_id');
		$startPage = array_values(array_filter($importedContext->getPages(), static fn (array $page): bool => $page['page_type'] === Page::TYPE_STARTPAGE))[0];
		$this->assertEqualsCanonicalizing(array_values($importedRelationIds), array_column($startPage['content'], 'node_rel_id'));
		$gridPage = array_values(array_filter($this->service->getPagesWithWidgets($importedContext), static fn (array $page): bool => $page['page_type'] === Page::TYPE_GRID))[0];
		$this->assertSame(
			['Node 4151' => $importedRelationIds[5151], 'Node 4152' => $importedRelationIds[5152], 'Node 4153' => $importedRelationIds[5153]],
			array_combine(array_column($gridPage['widgets'], 'title'), array_map(static fn (array $widget): ?int => $widget['source']['nodeRelId'] ?? null, $gridPage['widgets'])),
		);
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

		$this->widgetService->insertAll($page->getId(), [
			new WidgetInput(null, 'header', 'Welcome', 'home', 'Where to start', 0, 0, 12, 2, ['heading' => 'Projects', 'subheading' => '', 'textAlign' => 'center', 'backgroundColor' => '#123456', 'textColor' => ''], null),
			new WidgetInput(null, 'data', null, null, null, 0, 2, 12, 5, [], $relation->getId()),
		]);

		return [$context, $relation, $page->getId()];
	}
}
