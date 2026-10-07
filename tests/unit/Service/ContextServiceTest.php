<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
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
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
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
	private MockObject $menuItemMapper;
	private MockObject $tableMapper;
	private MockObject $viewMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$this->menuItemMapper = $this->createMock(MenuItemMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$permissionsService = $this->createMock(PermissionsService::class);
		$userManager = $this->createMock(IUserManager::class);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$dbConnection = $this->createMock(IDBConnection::class);
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
			$dbConnection,
			$shareService,
			false,
			$navigationManager,
			$urlGenerator,
			$this->tableMapper,
			$this->viewMapper,
			$this->menuItemMapper,
		);
	}

	private function contextWithMenu(int $id, array $menuItems): Context {
		$context = new Context();
		$context->id = $id;
		$context->setName('Intake portal');
		$context->setNodes([]);
		$context->setPages([]);
		$context->setMenuItems($menuItems);
		return $context;
	}

	private function expectMenuReplacement(int $contextId, array &$inserted): void {
		$this->menuItemMapper->expects($this->once())->method('deleteAllByContextId')->with($contextId);
		$this->menuItemMapper->method('insert')->willReturnCallback(static function (MenuItem $item) use (&$inserted): MenuItem {
			$item->setId(count($inserted) + 1);
			$inserted[] = $item;
			return $item;
		});
	}

	public function testUpdateReplacesTheMenuInOrderAndSlugifiesLabels(): void {
		$context = $this->contextWithMenu(7, []);
		$this->contextMapper->method('findById')->with(7, 'user-1')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$updated = $this->service->update(7, 'user-1', null, null, null, null, 'intake', [
			['label' => 'Intake home', 'targetType' => 'view', 'targetId' => 6, 'slug' => 'home'],
			['label' => 'Welcome table!', 'targetType' => 'table', 'targetId' => 4],
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://docs.nextcloud.com', 'targetId' => 99],
		]);

		$this->assertSame('intake', $updated->getSlug());
		$this->assertSame([10, 20, 30], array_map(static fn (MenuItem $item) => $item->getOrder(), $inserted));
		$this->assertSame(['home', 'welcome-table', 'docs'], array_map(static fn (MenuItem $item) => $item->getSlug(), $inserted));
		$this->assertSame([6, 4, null], array_map(static fn (MenuItem $item) => $item->getTargetId(), $inserted));
		$this->assertSame([null, null, 'https://docs.nextcloud.com'], array_map(static fn (MenuItem $item) => $item->getUrl(), $inserted));
		$this->assertCount(3, $updated->getMenuItems());
		$this->assertSame('Welcome table!', $updated->getMenuItems()[1]['label']);
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

	public function testUpdateRejectsAMalformedSlug(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));

		$this->expectException(\InvalidArgumentException::class);
		$this->service->update(7, 'user-1', null, null, null, null, 'Not A Slug');
	}

	public function testUpdateClearsTheSlugWhenItIsBlank(): void {
		$context = $this->contextWithMenu(7, []);
		$context->setSlug('old');
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);

		$updated = $this->service->update(7, 'user-1', null, null, null, null, '  ');

		$this->assertNull($updated->getSlug());
	}

	public function testCreateStoresSlugAndMenuItems(): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->setId(8);
			return $context;
		});
		$this->pageMapper->method('insert')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(8, $inserted);

		$created = $this->service->create('Intake portal', 'heart', ' desc ', [], 'user-1', 0, 'intake', [
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://example.org'],
		]);

		$this->assertSame('intake', $created->getSlug());
		$this->assertSame('desc', $created->getDescription());
		$this->assertCount(1, $created->getMenuItems());
		$this->assertSame('docs', $inserted[0]->getSlug());
	}

	public function testDeleteRemovesTheMenuItems(): void {
		$context = $this->contextWithMenu(7, []);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->willReturn([]);
		$this->menuItemMapper->expects($this->once())->method('deleteAllByContextId')->with(7);

		$this->service->delete(7, 'user-1');
	}

	/**
	 * The export and the import resolution are private because their only entry points
	 * need the server container; they are exercised directly here.
	 */
	private function callPrivate(string $method, array $arguments): mixed {
		$reflection = new \ReflectionMethod($this->service, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($this->service, $arguments);
	}

	public function testExportMenuItemsUsesUuidsAndCarriesTablelessViews(): void {
		$table = new Table();
		$table->setId(4);
		$table->setUuid('table-uuid');
		$gridView = new View();
		$gridView->setId(6);
		$gridView->setUuid('view-uuid');
		$gridView->setTitle('Intake home');
		$gridView->setType(View::TYPE_GRID);
		$gridView->setSlug('home');
		$gridView->setGridArray(['widgets' => [['id' => 'w-1', 'type' => 'data', 'content' => ['targetType' => 'table', 'targetId' => 4]]], 'layout' => []]);
		$this->tableMapper->method('find')->with(4)->willReturn($table);
		$this->viewMapper->method('find')->with(6)->willReturn($gridView);

		$context = $this->contextWithMenu(7, [
			['label' => 'Intake home', 'icon' => null, 'targetType' => 'view', 'targetId' => 6, 'url' => null, 'slug' => 'home'],
			['label' => 'Welcome', 'icon' => null, 'targetType' => 'table', 'targetId' => 4, 'url' => null, 'slug' => 'welcome'],
			['label' => 'Docs', 'icon' => null, 'targetType' => 'url', 'targetId' => null, 'url' => 'https://example.org', 'slug' => 'docs'],
		]);

		[$menuItems, $gridViews] = $this->callPrivate('exportMenuItems', [$context]);

		$this->assertSame(['view-uuid', 'table-uuid', null], array_column($menuItems, 'targetUuid'));
		$this->assertArrayNotHasKey('targetId', $menuItems[0]);
		$this->assertCount(1, $gridViews);
		$this->assertSame('view-uuid', $gridViews[0]['uuid']);
		$this->assertSame('table-uuid', $gridViews[0]['grid']['widgets'][0]['content']['targetUuid']);
	}

	public function testExportSkipsMenuItemsWhoseTargetIsGone(): void {
		$this->tableMapper->method('find')->willThrowException(new \OCP\AppFramework\Db\DoesNotExistException('gone'));
		$context = $this->contextWithMenu(7, [
			['label' => 'Gone', 'icon' => null, 'targetType' => 'table', 'targetId' => 99, 'url' => null, 'slug' => 'gone'],
			['label' => 'Docs', 'icon' => null, 'targetType' => 'url', 'targetId' => null, 'url' => 'https://example.org', 'slug' => 'docs'],
		]);

		[$menuItems] = $this->callPrivate('exportMenuItems', [$context]);

		$this->assertSame(['Docs'], array_column($menuItems, 'label'));
	}

	public function testResolveMenuItemsMapsUuidsToLocalIdsAndDropsUnknownTargets(): void {
		$table = new Table();
		$table->setId(4);
		$this->tableMapper->method('findByUuid')->willReturnCallback(static function (string $uuid) use ($table): Table {
			if ($uuid === 'table-uuid') {
				return $table;
			}
			throw new \OCP\AppFramework\Db\DoesNotExistException('unknown');
		});
		$view = new View();
		$view->setId(6);
		$this->viewMapper->method('findByUuid')->willReturnCallback(static fn (string $uuid): ?View => $uuid === 'view-uuid' ? $view : null);

		$resolved = $this->callPrivate('resolveMenuItems', [[
			['label' => 'Intake home', 'targetType' => 'view', 'targetUuid' => 'view-uuid', 'slug' => 'home'],
			['label' => 'Welcome', 'targetType' => 'table', 'targetUuid' => 'table-uuid'],
			['label' => 'Missing', 'targetType' => 'table', 'targetUuid' => 'nope'],
			['label' => 'Weird', 'targetType' => 'kanban', 'targetUuid' => 'x'],
			['label' => '   ', 'targetType' => 'url', 'url' => 'https://example.org'],
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://example.org'],
		]]);

		$this->assertSame(['Intake home', 'Welcome', 'Docs'], array_column($resolved, 'label'));
		$this->assertSame([6, 4, null], array_column($resolved, 'targetId'));
		$this->assertSame('https://example.org', $resolved[2]['url']);
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

	private function buildPageContent(int $id, int $pageId, int $nodeRelId, int $order): PageContent {
		$pageContent = new PageContent();
		$pageContent->id = $id;
		$pageContent->setPageId($pageId);
		$pageContent->setNodeRelId($nodeRelId);
		$pageContent->setOrder($order);

		return $pageContent;
	}
}
