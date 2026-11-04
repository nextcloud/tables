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
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Db\WidgetSourceMapper;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\TableScheme;
use OCA\Tables\Model\WidgetInput;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\MenuService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\TableService;
use OCA\Tables\Service\ViewService;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\AppFramework\Db\DoesNotExistException;
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
	private MenuService $menuService;
	private PermissionsService&MockObject $permissionsService;
	private TableMapper&MockObject $tableMapper;
	private ViewMapper&MockObject $viewMapper;
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
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$menuItemMapper = new MenuItemMapper($this->connectionAdapter);
		$this->menuService = new MenuService($this->contextMapper, $menuItemMapper, $this->permissionsService, $this->tableMapper, $this->viewMapper, $this->connectionAdapter);
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
			$this->viewMapper,
			$this->widgetService,
			new GridWidgetTypes($l10n),
			$menuItemMapper,
			$this->menuService,
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

		$this->widgetService->insertAll($page->getId(), [
			new WidgetInput(null, 'header', 'Welcome', 'home', 'Where to start', 0, 0, 12, 2, ['heading' => 'Projects', 'subheading' => '', 'textAlign' => 'center', 'backgroundColor' => '#123456', 'textColor' => ''], null),
			new WidgetInput(null, 'data', null, null, null, 0, 2, 12, 5, [], $relation->getId()),
		]);

		return [$context, $relation, $page->getId()];
	}

	public function testASchemeCarriesPagesWithWidgetsOnTheirTablesAndViewsByUuid(): void {
		$this->permissionsService->method('canManageNodeById')->willReturnCallback(static fn (int $type, int $id): bool => $id !== 5199);
		$this->permissionsService->method('canManageContext')->willReturn(true);
		$this->tableMapper->method('findMany')->willReturnCallback(static fn (array $ids): array => array_combine($ids, array_map(static fn (): Table => new Table(), $ids)));
		$node = static fn (int $id, int $type): array => ['id' => $id, 'type' => $type, 'permissions' => Application::PERMISSION_MANAGE];
		$source = $this->service->create('Source', 'tables', '', [$node(4181, Application::NODE_TYPE_TABLE), $node(4182, Application::NODE_TYPE_VIEW)], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $source->getId();
		$relationIds = array_column($this->contextMapper->findById($source->getId(), self::OWNER)->getNodes(), 'id', 'node_id');
		$overview = $this->insertGridPage($source->getId(), 'overview', 'Overview');
		$this->widgetService->insert($overview->getId(), 'header', null, ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 2], ['heading' => 'Source'], null);
		$this->widgetService->insert($overview->getId(), 'data', 'Tasks', ['gridX' => 0, 'gridY' => 2, 'gridWidth' => 12, 'gridHeight' => 5], [], $relationIds[4181]);
		$this->widgetService->insert($overview->getId(), 'data', 'Open tasks', ['gridX' => 0, 'gridY' => 7, 'gridWidth' => 12, 'gridHeight' => 5], [], $relationIds[4182]);
		$notes = $this->insertGridPage($source->getId(), null, 'Notes');
		$this->menuService->create($source->getId(), 'Notes', null, null, ['pageId' => $notes->getId()], self::OWNER);

		$tableService = $this->createMock(TableService::class);
		$tableService->method('find')->willReturnCallback(static fn (int $id): Table => self::withUuid(new Table(), $id, 'table-' . $id));
		$tableService->method('getScheme')->willReturn($this->createMock(TableScheme::class));
		$tableService->method('findAll')->willReturn([]);
		$viewService = $this->createMock(ViewService::class);
		$viewService->method('find')->willReturnCallback(static function (int $id): View {
			$view = self::withUuid(new View(), $id, 'view-' . $id);
			$view->setTableId(4181);
			return $view;
		});
		$scheme = $this->withServices([TableService::class => $tableService, ViewService::class => $viewService], fn (): array => json_decode(json_encode($this->service->getScheme($source->getId(), self::OWNER)), true));
		$this->assertSame(['targetType' => 'view', 'targetUuid' => 'view-4182'], array_diff_key(array_values(array_filter($scheme['pages'], static fn (array $page): bool => $page['technical_name'] === 'overview'))[0]['widgets'][2]['source'], ['nodeRelId' => true]));
		// the tables of the scheme are not under test here
		$scheme['tables'] = [];
		$overviewWidgets = &$scheme['pages'][array_key_first(array_filter($scheme['pages'], static fn (array $page): bool => $page['technical_name'] === 'overview'))]['widgets'];
		$overviewWidgets[] = ['type' => 'data', 'title' => 'Elsewhere', 'gridX' => 0, 'gridY' => 12, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => [], 'source' => ['targetType' => 'table', 'targetUuid' => 'table-4199']];
		// here and manageable, but not a node of the scheme: a widget does not add it
		$overviewWidgets[] = ['type' => 'data', 'title' => 'Not a resource', 'gridX' => 0, 'gridY' => 17, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => [], 'source' => ['targetType' => 'table', 'targetUuid' => 'table-4198']];
		unset($overviewWidgets);
		$target = $this->service->create('Target', 'tables', '', [], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $targetId = $target->getId();

		$this->tableMapper->method('findByUuid')->willReturnCallback(static fn (string $uuid): Table => match ($uuid) {
			'table-4181' => self::withUuid(new Table(), 5181, $uuid),
			'table-4198' => self::withUuid(new Table(), 5198, $uuid),
			'table-4199' => self::withUuid(new Table(), 5199, $uuid),
			default => throw new DoesNotExistException('No table'),
		});
		$this->viewMapper->method('findByUuid')->willReturnCallback(static fn (string $uuid): View => $uuid === 'view-4182' ? self::withUuid(new View(), 5182, $uuid) : throw new DoesNotExistException('No view'));
		$preview = $this->withServices([TableService::class => $tableService], fn (): array => $this->service->compareSchemeChanges($targetId, $scheme, self::OWNER));

		$this->assertSame([
			['Elsewhere', 'its table is not one of the resources of the application'],
			['Not a resource', 'its table is not one of the resources of the application'],
		], array_map(static fn (array $skip): array => [$skip['widget'], $skip['reason']], $preview['pages']['skipped']));
		$this->assertSame([], $preview['menu']['skipped']);
		$this->service->addNodeToContext($this->contextMapper->findById($targetId, self::OWNER), 5181, Application::NODE_TYPE_TABLE, Application::PERMISSION_MANAGE);
		$this->service->addNodeToContext($this->contextMapper->findById($targetId, self::OWNER), 5182, Application::NODE_TYPE_VIEW, Application::PERMISSION_MANAGE);
		$this->service->importScheme($targetId, 'Target', 'tables', '', $scheme['nodes'], ['addTables' => [], 'modifyTables' => []], self::OWNER, $preview['menu']['to'], $scheme['pages']);

		$target = $this->contextMapper->findById($targetId, self::OWNER);
		$targetRelationIds = array_column($target->getNodes(), 'id', 'node_id');
		$this->assertEqualsCanonicalizing([5181, 5182], array_keys($targetRelationIds));
		$pages = $this->service->getPagesWithWidgets($target);
		$byTitle = array_column(array_filter($pages, static fn (array $page): bool => $page['title'] !== null), null, 'title');
		$this->assertSame('overview', $byTitle['Overview']['technical_name']);
		$this->assertSame(
			[['header', null], ['data', $targetRelationIds[5181]], ['data', $targetRelationIds[5182]]],
			array_map(static fn (array $widget): array => [$widget['type'], $widget['source']['nodeRelId'] ?? null], $byTitle['Overview']['widgets']),
		);
		$notesId = $byTitle['Notes']['id'];
		$this->assertSame('page_' . $notesId, $byTitle['Notes']['technical_name']);
		$this->assertSame([['pageId' => $notesId]], array_column(array_values(array_filter($this->menuService->findAll($targetId, self::OWNER), static fn (array $item): bool => $item['title'] === 'Notes')), 'target'));
	}

	public function testAPreviewCountsTheTablesAndViewsTheImportAddsAsResources(): void {
		$this->tableMapper->method('findByUuid')->willThrowException(new DoesNotExistException('No table'));
		$this->viewMapper->method('findByUuid')->willThrowException(new DoesNotExistException('No view'));
		$tableService = $this->createMock(TableService::class);
		$tableService->method('findAll')->willReturn([]);
		$target = $this->service->create('Target', 'tables', '', [], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $target->getId();
		$widget = static fn (string $title, string $type, string $uuid): array => ['type' => 'data', 'title' => $title, 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 12, 'gridHeight' => 5, 'settings' => [], 'source' => ['targetType' => $type, 'targetUuid' => $uuid]];

		$preview = $this->withServices([TableService::class => $tableService], fn (): array => $this->service->compareSchemeChanges($target->getId(), [
			'nodes' => [
				['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => 'new-table', 'permissions' => 1],
				['node_type' => Application::NODE_TYPE_VIEW, 'node_uuid' => 'new-view', 'permissions' => 1],
				['node_type' => Application::NODE_TYPE_VIEW, 'node_uuid' => 'gone-view', 'permissions' => 1],
			],
			'tables' => [['uuid' => 'new-table', 'views' => [['uuid' => 'new-view']]]],
			'pages' => [['page_type' => Page::TYPE_GRID, 'technical_name' => 'overview', 'widgets' => [
				$widget('Added table', 'table', 'new-table'),
				$widget('Added view', 'view', 'new-view'),
				$widget('Gone', 'view', 'gone-view'),
			]]],
		], self::OWNER));

		$this->assertSame(['Added table', 'Added view'], array_column($preview['pages']['to'][0]['widgets'], 'title'));
		$this->assertSame(['Gone'], array_column($preview['pages']['skipped'], 'widget'));
	}

	private function insertGridPage(int $contextId, ?string $technicalName, string $title): Page {
		$page = new Page();
		$page->setContextId($contextId);
		$page->setPageType(Page::TYPE_GRID);
		$page->setTitle($title);
		if ($technicalName === null) {
			return $this->pageMapper->insertWithDefaultName($page);
		}
		$page->setTechnicalName($technicalName);
		return $this->pageMapper->insert($page);
	}

	/**
	 * @template T of Table|View
	 * @param T $node
	 * @return T
	 */
	private static function withUuid(Table|View $node, int $id, string $uuid): Table|View {
		$node->id = $id;
		$node->setUuid($uuid);
		return $node;
	}

	/**
	 * Runs a callback with services of the server container replaced, as the scheme
	 * export and preview get them from there.
	 *
	 * @param array<class-string, object> $services
	 */
	private function withServices(array $services, callable $callback): mixed {
		$container = \OC::$server->getAppContainerForService(TableService::class) ?? \OC::$server;
		$real = [];
		foreach ($services as $class => $service) {
			$real[$class] = \OCP\Server::get($class);
			$container->registerService($class, static fn () => $service);
		}
		try {
			return $callback();
		} finally {
			foreach ($real as $class => $service) {
				$container->registerService($class, static fn () => $service);
			}
		}
	}
}
