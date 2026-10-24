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
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
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
		);
		$this->service = new ContextService(
			$this->contextMapper,
			$this->nodeRelMapper,
			$this->pageMapper,
			new PageContentMapper($this->connectionAdapter),
			$this->createMock(LoggerInterface::class),
			$this->createMock(PermissionsService::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IEventDispatcher::class),
			$this->connectionAdapter,
			$this->createMock(ShareService::class),
			false,
			$this->createMock(INavigationManager::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(TableMapper::class),
			$this->createMock(ViewMapper::class),
			$this->widgetService,
			new GridWidgetTypes($l10n),
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
}
