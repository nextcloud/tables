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
	private MockObject $permissionsService;

	protected function setUp(): void {
		parent::setUp();

		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$this->menuItemMapper = $this->createMock(MenuItemMapper::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$logger = $this->createMock(LoggerInterface::class);
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
			$this->permissionsService,
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
			$item->setId(count($inserted) + 1);
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
			$relation->setId(200 + count($relations));
			$relations[] = $relation;
			return $relation;
		});
	}

	public function testUpdateReplacesTheMenuInOrderAndDerivesTechnicalNamesFromLabels(): void {
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
		$this->assertSame(['home', 'welcome_table', 'docs'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
		$this->assertSame([6, 4, null], array_map(static fn (MenuItem $item) => $item->getTargetId(), $inserted));
		$this->assertSame([null, null, 'https://docs.nextcloud.com'], array_map(static fn (MenuItem $item) => $item->getUrl(), $inserted));
		$this->assertCount(3, $updated->getMenuItems());
		$this->assertSame('Welcome table!', $updated->getMenuItems()[1]['label']);
	}

	public function testUpdateStoresAChildUnderItsParentAndKeepsTheOrder(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->expectExistingTargets([6 => new View()], [4 => new Table()]);
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$relations = [];
		$this->expectNodeInsertion($relations);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$updated = $this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Cases', 'targetType' => 'table', 'targetId' => 4],
			['label' => 'Open cases', 'targetType' => 'view', 'targetId' => 6, 'parentIndex' => 0],
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://example.org'],
		]));

		$byLabel = [];
		foreach ($inserted as $item) {
			$byLabel[$item->getLabel()] = $item;
		}
		$this->assertNull($byLabel['Cases']->getParentId());
		$this->assertSame($byLabel['Cases']->getId(), $byLabel['Open cases']->getParentId());
		$this->assertSame([10, 20, 30], [$byLabel['Cases']->getOrder(), $byLabel['Open cases']->getOrder(), $byLabel['Docs']->getOrder()]);
		$this->assertCount(3, $updated->getMenuItems());
	}

	public function testReplaceMenuRefusesAChildUnderAChild(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		// built without createListFromInputArray, which already refuses this, to reach the service's own guard
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('top-level');
		$this->service->update(7, 'user-1', null, null, null, null, [
			new MenuItemInput('A', null, 'url', null, 'https://a.example', null),
			new MenuItemInput('B', null, 'url', null, 'https://b.example', null, 0),
			new MenuItemInput('C', null, 'url', null, 'https://c.example', null, 1),
		]);
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
			$content->setId(501);
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
		$this->assertSame(['id' => 200, 'node_id' => 6, 'node_type' => Application::NODE_TYPE_VIEW, 'permissions' => Application::PERMISSION_READ], $updated->getNodes()[200]);
		$this->assertSame(200, $updated->getPages()[301]['content'][501]['node_rel_id']);
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

		$this->assertSame(['home', 'docs_2', 'docs', 'docs_3'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
	}

	public function testDerivedTechnicalNamesGetASuffixWhenTheMenuAlreadyUsesThem(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithMenu(7, []));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$inserted = [];
		$this->expectMenuReplacement(7, $inserted);

		$this->service->update(7, 'user-1', null, null, null, null, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'url' => 'https://example.org/a'],
			['label' => 'Docs', 'url' => 'https://example.org/b'],
			['label' => 'Other', 'url' => 'https://example.org/c', 'technicalName' => 'docs_2'],
		]));

		$this->assertSame(['docs', 'docs_3', 'docs_2'], array_map(static fn (MenuItem $item) => $item->getTechnicalName(), $inserted));
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
			$context->setId(8);
			return $context;
		});
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page): Page {
			$page->setId(3);
			return $page;
		});
		$inserted = [];
		$this->expectMenuReplacement(8, $inserted);

		$created = $this->service->create('Intake portal', 'heart', ' desc ', [], 'user-1', 0, MenuItemInput::createListFromInputArray([
			['label' => 'Docs', 'targetType' => 'url', 'url' => 'https://example.org'],
		]));

		$this->assertSame('desc', $created->getDescription());
		$this->assertCount(1, $created->getMenuItems());
		$this->assertSame('docs', $inserted[0]->getTechnicalName());
	}

	public function testDeleteRemovesTheMenuOfTheApplication(): void {
		$context = new Context();
		$context->id = 7;
		$this->contextMapper->method('findById')->with(7, 'alice')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->willReturn([]);

		$this->menuItemMapper->expects($this->once())->method('deleteAllByContextId')->with(7);

		$this->service->delete(7, 'alice');
	}

	/**
	 * On SQLite a transaction that reads before it writes fails at once when another request
	 * writes meanwhile, so the delete takes the write lock first.
	 */
	public function testDeleteRemovesTheApplicationBeforeWhatBelongsToIt(): void {
		$context = new Context();
		$context->id = 7;
		$this->contextMapper->method('findById')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->willReturn([]);
		$calls = [];
		$this->contextMapper->method('delete')->willReturnCallback(static function (Context $deleted) use (&$calls): Context {
			$calls[] = 'application';
			return $deleted;
		});
		$this->menuItemMapper->method('deleteAllByContextId')->willReturnCallback(static function () use (&$calls): void {
			$calls[] = 'menu';
		});

		$this->service->delete(7, 'alice');

		$this->assertSame(['application', 'menu'], $calls);
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
