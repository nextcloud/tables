<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\ConflictError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\MenuOrderEntry;
use OCA\Tables\Model\MenuTarget;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\MenuService;
use OCA\Tables\Service\PageService;
use OCA\Tables\Service\PermissionsService;
use OCA\Tables\Service\ShareService;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Service\WidgetService;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Runs against the database.
 */
class MenuServiceTest extends DatabaseTestCase {
	private const OWNER = 'menu-service-owner';
	private const TABLE = 4151;
	private const TABLE_UUID = 'a5d8e2c4-4151-4cde-9f00-000000004151';
	private const OTHER_TABLE = 4152;
	private const OTHER_TABLE_UUID = 'a5d8e2c4-4152-4cde-9f00-000000004152';
	private const VIEW = 4153;
	private const VIEW_UUID = 'a5d8e2c4-4153-4cde-9f00-000000004153';
	private const SHARED_VIEW_UUID = 'a5d8e2c4-4154-4cde-9f00-000000004154';

	private MenuService $menuService;
	private ContextService $contextService;
	private PageService $pageService;
	private MenuItemMapper $menuItemMapper;
	private ContextMapper $contextMapper;
	private ViewMapper&MockObject $viewMapper;
	private PermissionsService&MockObject $permissionsService;
	private bool $canManage = true;
	/** @var int[] */
	private array $contextIds = [];

	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$userHelper = $this->createMock(UserHelper::class);
		$userHelper->method('getGroupIdsForUser')->willReturn([]);
		$contextMapper = new ContextMapper($this->connectionAdapter, $userHelper, $this->createMock(GroupHelper::class));
		$this->contextMapper = $contextMapper;
		$pageMapper = new PageMapper($this->connectionAdapter);
		$pageContentMapper = new PageContentMapper($this->connectionAdapter);
		$widgetService = new WidgetService(new WidgetMapper($this->connectionAdapter), new ContextNodeRelationMapper($this->connectionAdapter), $this->connectionAdapter);
		$this->menuItemMapper = new MenuItemMapper($this->connectionAdapter);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->permissionsService->method('canManageContext')->willReturnCallback(fn (): bool => $this->canManage);
		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('findIdToTitleMap')->willReturnCallback(static fn (array $ids): array => in_array(self::TABLE, $ids, true) ? [self::TABLE => 'Projects'] : []);
		$table = new Table();
		$table->id = self::TABLE;
		$table->setUuid(self::TABLE_UUID);
		$table->setTitle('Projects');
		$tableMapper->method('findMany')->willReturnCallback(static fn (array $ids): array => in_array(self::TABLE, $ids, true) ? [self::TABLE => $table] : []);
		$otherTable = new Table();
		$otherTable->id = self::OTHER_TABLE;
		$otherTable->setUuid(self::OTHER_TABLE_UUID);
		$tableMapper->method('findByUuid')->willReturnCallback(static fn (string $uuid): Table => [self::TABLE_UUID => $table, self::OTHER_TABLE_UUID => $otherTable][$uuid] ?? throw new DoesNotExistException('No table'));
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->menuService = new MenuService($contextMapper, new ContextNodeRelationMapper($this->connectionAdapter), $this->menuItemMapper, $this->permissionsService, $tableMapper, $this->viewMapper, $this->connectionAdapter);

		$this->contextService = new ContextService(
			$contextMapper,
			new ContextNodeRelationMapper($this->connectionAdapter),
			$pageMapper,
			$pageContentMapper,
			$this->createMock(LoggerInterface::class),
			$this->permissionsService,
			$this->createMock(IUserManager::class),
			$this->createMock(AuditLogServiceInterface::class),
			$this->connectionAdapter,
			$this->createMock(ShareService::class),
			false,
			$this->createMock(INavigationManager::class),
			$this->createMock(IURLGenerator::class),
			$tableMapper,
			$this->viewMapper,
			$widgetService,
			new GridWidgetTypes($l10n),
			$this->menuItemMapper,
			$this->menuService,
		);
		$this->pageService = new PageService($contextMapper, $pageMapper, $pageContentMapper, $widgetService, new GridWidgetTypes($l10n), $this->connectionAdapter, $this->menuItemMapper);
	}

	protected function tearDown(): void {
		foreach ($this->contextIds as $contextId) {
			$this->contextService->delete($contextId, self::OWNER);
		}
		parent::tearDown();
	}

	public function testANewApplicationOpensItsStartPage(): void {
		[$contextId, $startPageId] = $this->createApplication();

		$menu = $this->menu($contextId);

		$this->assertSame([[
			'id' => $menu[0]['id'],
			'contextId' => $contextId,
			'parentId' => null,
			'title' => null,
			'displayTitle' => 'Sales',
			'icon' => null,
			'description' => null,
			'target' => ['pageId' => $startPageId],
		]], $menu);
	}

	public function testTheFirstItemOfAMenuWithoutItemsComesAfterTheStartPage(): void {
		[$contextId, $startPageId] = $this->createApplication();
		$this->menuItemMapper->deleteByContextId($contextId);

		$this->menuService->create($contextId, 'Reports', null, null, null, self::OWNER);

		$this->assertSame([['pageId' => $startPageId], null], array_column($this->menu($contextId), 'target'));
	}

	public function testAnItemWithoutATitleShowsTheTitleOfWhatItOpens(): void {
		[$contextId, , $pageId, $nodeRelId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['nodeRelId' => $nodeRelId]), self::OWNER);
		$this->menuService->create($contextId, null, 'star', 'Our site', MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER);
		$this->menuService->create($contextId, 'Reports', null, null, null, self::OWNER);

		$menu = $this->menu($contextId);

		$this->assertSame(['Sales', 'Overview', 'Projects', 'https://nextcloud.com', 'Reports'], array_column($menu, 'displayTitle'));
		$this->assertSame(['star', 'Our site'], [$menu[3]['icon'], $menu[3]['description']]);
	}

	public static function invalidItems(): array {
		return [
			'a group without a title' => [null, null, 'A group needs a title.'],
			'a page of another application' => [null, ['pageId' => -1], 'The target of a menu item'],
			'a table outside the application' => [null, ['nodeRelId' => -1], 'The target of a menu item'],
			'two targets' => [null, ['url' => 'https://nextcloud.com', 'nodeRelId' => -1], 'The target of a menu item'],
			'a link that is not http' => [null, ['url' => 'javascript:alert(1)'], 'A link is an http or https address'],
			'an ftp link' => [null, ['url' => 'ftp://example.com/file'], 'A link is an http or https address'],
			'a link without a host' => [null, ['url' => 'https:///path'], 'A link is an http or https address'],
			'an unknown icon' => ['Reports', null, 'The icon of a menu item must be the name of an icon.', 'no-such-icon'],
			'a long title' => [str_repeat('a', 201), null, 'at most 200 characters'],
		];
	}

	/**
	 * @dataProvider invalidItems
	 */
	public function testAnInvalidItemIsRefused(?string $title, ?array $target, string $message, ?string $icon = null): void {
		[$contextId] = $this->createApplication();

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage($message);
		$this->menuService->create($contextId, $title, $icon, null, $target === null ? null : MenuTarget::fromArray($target), self::OWNER);
	}

	public function testALinkMayHoldAnyCharacterAPersonTypes(): void {
		[$contextId] = $this->createApplication();

		foreach (['https://www.müller.de/', 'https://example.com/straße', 'https://my_host.example/', ' HTTPS://nextcloud.com '] as $url) {
			$this->assertSame(['url' => trim($url)], $this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['url' => $url]), self::OWNER)['target']);
		}
	}

	public function testAnItemOpensAtMostOneThing(): void {
		[$contextId, , $pageId] = $this->createApplication();
		$item = $this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER);
		$stored = $this->menuItemMapper->findById($item['id']);
		$stored->setPageId($pageId);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('not more than one');
		(new \ReflectionMethod(MenuService::class, 'assertValid'))->invoke($this->menuService, $stored);
	}

	public function testOnlyAManagerSeesAnEmptyGroup(): void {
		[$contextId] = $this->createApplication();
		$this->menuService->create($contextId, 'Reports', null, null, null, self::OWNER);

		$this->assertCount(2, $this->menu($contextId));
		$this->canManage = false;
		$this->assertSame(['Sales'], array_column($this->menu($contextId), 'displayTitle'));
	}

	public function testAMenuHoldsAtMost200Items(): void {
		[$contextId] = $this->createApplication();
		for ($i = 1; $i < MenuItem::MAX_ITEMS_PER_APPLICATION - 1; $i++) {
			$item = new MenuItem();
			$item->setContextId($contextId);
			$item->setOrder($i);
			$item->setTitle('Group ' . $i);
			$this->menuItemMapper->insert($item);
		}
		$this->menuService->create($contextId, 'The last one', null, null, null, self::OWNER);
		$this->assertCount(MenuItem::MAX_ITEMS_PER_APPLICATION, $this->menuItemMapper->findByContextId($contextId));

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('at most 200 items');
		$this->menuService->create($contextId, 'One more', null, null, null, self::OWNER);
	}

	public function testAnUpdateKeepsWhatIsLeftOutAndATargetReplacesThePreviousOne(): void {
		[$contextId, , $pageId] = $this->createApplication();
		$item = $this->menuService->create($contextId, 'Site', 'star', 'Ours', MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER);

		$updated = $this->menuService->update($contextId, $item['id'], '', null, '', MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);

		$this->assertSame([null, 'Overview', 'star', null, ['pageId' => $pageId]], [$updated['title'], $updated['displayTitle'], $updated['icon'], $updated['description'], $updated['target']]);
		$this->assertSame($updated, $this->menu($contextId)[1]);
	}

	public function testAGroupThatHoldsItemsCannotOpenSomething(): void {
		[$contextId] = $this->createApplication();
		$menu = $this->menu($contextId);
		$group = $this->menuService->create($contextId, 'Reports', null, null, null, self::OWNER);
		$this->menuService->saveOrder($contextId, MenuOrderEntry::listFromArray([['id' => $group['id'], 'parentId' => null], ['id' => $menu[0]['id'], 'parentId' => $group['id']]]), $this->version($contextId), self::OWNER);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Move its items out first');
		$this->menuService->update($contextId, $group['id'], null, null, null, MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER);
	}

	public function testTheOrderSavesPositionAndGroupsAndDeletingAGroupMovesItsItemsUp(): void {
		[$contextId] = $this->createApplication();
		$start = $this->menu($contextId)[0];
		$site = $this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER);
		$group = $this->menuService->create($contextId, 'Links', null, null, null, self::OWNER);

		$saved = $this->menuService->saveOrder($contextId, MenuOrderEntry::listFromArray([
			['id' => $group['id'], 'parentId' => null],
			['id' => $site['id'], 'parentId' => $group['id']],
			['id' => $start['id'], 'parentId' => null],
		]), $this->version($contextId), self::OWNER)['menu'];
		$this->assertSame([[$group['id'], null], [$site['id'], $group['id']], [$start['id'], null]], array_map(static fn (array $item): array => [$item['id'], $item['parentId']], $saved));

		$this->menuService->delete($contextId, $group['id'], self::OWNER);
		$this->assertSame([[$site['id'], null], [$start['id'], null]], array_map(static fn (array $item): array => [$item['id'], $item['parentId']], $this->menu($contextId)));
	}

	public function testASaveBasedOnAnOlderVersionOfTheOrderIsRefused(): void {
		[$contextId] = $this->createApplication();
		$start = $this->menu($contextId)[0]['id'];
		$group = $this->menuService->create($contextId, 'Links', null, null, null, self::OWNER)['id'];
		$version = $this->version($contextId);
		$order = MenuOrderEntry::listFromArray([['id' => $group, 'parentId' => null], ['id' => $start, 'parentId' => $group]]);

		$saved = $this->menuService->saveOrder($contextId, $order, $version, self::OWNER);
		$this->assertSame($this->version($contextId), $saved['version']);
		$this->assertNotSame($version, $saved['version']);

		$this->expectException(ConflictError::class);
		$this->menuService->saveOrder($contextId, MenuOrderEntry::listFromArray([['id' => $start, 'parentId' => null], ['id' => $group, 'parentId' => null]]), $version, self::OWNER);
	}

	public static function changesInBetween(): array {
		return [
			'an item added' => [static fn (MenuService $service, int $contextId, int $itemId) => $service->create($contextId, 'Reports', null, null, null, self::OWNER)],
			'an item deleted' => [static fn (MenuService $service, int $contextId, int $itemId) => $service->delete($contextId, $itemId, self::OWNER)],
		];
	}

	/**
	 * @dataProvider changesInBetween
	 */
	public function testAListBuiltBeforeAnotherChangeIsAConflict(\Closure $change): void {
		[$contextId] = $this->createApplication();
		$group = $this->menuService->create($contextId, 'Links', null, null, null, self::OWNER)['id'];
		$menu = $this->menu($contextId);
		$version = $this->version($contextId);
		$change($this->menuService, $contextId, $group);

		$this->expectException(ConflictError::class);
		$this->menuService->saveOrder($contextId, MenuOrderEntry::listFromArray(array_map(static fn (array $item): array => ['id' => $item['id'], 'parentId' => null], $menu)), $version, self::OWNER);
	}

	public static function invalidOrders(): array {
		return [
			'an item left out' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, null]], BadRequestError::class, 'lists every item'],
			'an item listed twice' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, null], [$start, null], [$start, null]], BadRequestError::class, 'listed twice'],
			'a parent that is not a group' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, $start], [$start, null]], BadRequestError::class, 'only sit in a group'],
			'an item before its group' => [static fn (int $start, int $group, int $site): array => [[$site, $group], [$group, null], [$start, null]], BadRequestError::class, 'only sit in a group'],
			'a group in a group' => [static fn (int $start, int $group, int $site): array => [[$start, null], [$group, $group], [$site, null]], BadRequestError::class, 'only sit in a group'],
			'an item of another application' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, null], [-1, null]], BadRequestError::class, 'is not an item of application'],
			'a group that is not in the list' => [static fn (int $start, int $group, int $site): array => [[$site, -1], [$group, null], [$start, null]], BadRequestError::class, 'only sit in a group'],
		];
	}

	/**
	 * @dataProvider invalidOrders
	 * @param \Closure(int, int, int): list<array{int, ?int}> $order
	 * @param class-string<\Throwable> $error
	 */
	public function testAnInvalidOrderSavesNothing(\Closure $order, string $error, string $message): void {
		[$contextId] = $this->createApplication();
		$start = $this->menu($contextId)[0]['id'];
		$group = $this->menuService->create($contextId, 'Links', null, null, null, self::OWNER)['id'];
		$site = $this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER)['id'];
		$before = $this->menu($contextId);

		try {
			$this->menuService->saveOrder($contextId, MenuOrderEntry::listFromArray(array_map(static fn (array $entry): array => ['id' => $entry[0], 'parentId' => $entry[1]], $order($start, $group, $site))), $this->version($contextId), self::OWNER);
			$this->fail('The order was saved');
		} catch (\Exception $e) {
			$this->assertInstanceOf($error, $e);
			$this->assertStringContainsString($message, $e->getMessage());
		}
		$this->assertSame($before, $this->menu($contextId));
	}

	public function testAnItemGoesWithItsTargetAndAnItemWhoseTargetIsGoneIsLeftOut(): void {
		[$contextId, $startPageId, $pageId, $nodeRelId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['nodeRelId' => $nodeRelId]), self::OWNER);
		$dangling = new MenuItem();
		$dangling->setContextId($contextId);
		$dangling->setOrder(9);
		$dangling->setPageId(-1);
		$this->menuItemMapper->insert($dangling);

		$this->pageService->delete($contextId, $pageId);
		$this->contextService->removeNodeFromContextAndPages($nodeRelId);

		$this->assertSame([['pageId' => $startPageId]], array_column($this->menu($contextId), 'target'));
		$this->assertCount(2, $this->menuItemMapper->findByContextId($contextId));
		$this->contextService->delete($contextId, self::OWNER);
		$this->contextIds = [];
		$this->assertSame([], $this->menuItemMapper->findByContextId($contextId));
	}

	public function testAnItemWhoseTableIsGoneIsLeftOutOfTheMenuAndOfItsOrder(): void {
		[$contextId] = $this->createApplication();
		[$relation] = $this->contextService->addNodeToContextAndStartpage($contextId, self::TABLE + 1, Application::NODE_TYPE_TABLE, 1, 100, self::OWNER);
		$item = new MenuItem();
		$item->setContextId($contextId);
		$item->setOrder(1);
		$item->setNodeRelId($relation->getId());
		$item = $this->menuItemMapper->insert($item);

		$menu = $this->menu($contextId);
		$this->assertSame(['Sales'], array_column($menu, 'displayTitle'));
		$saved = $this->menuService->saveOrder($contextId, MenuOrderEntry::listFromArray(array_map(static fn (array $entry): array => ['id' => $entry['id'], 'parentId' => $entry['parentId']], $menu)), $this->version($contextId), self::OWNER);
		$this->assertSame(['menu' => $menu, 'version' => $this->version($contextId)], $saved);

		$this->expectException(NotFoundError::class);
		$this->menuService->delete($contextId, $item->getId(), self::OWNER);
	}

	public function testAnItemIsNotFoundThroughAnotherApplication(): void {
		[$contextId] = $this->createApplication();
		[$otherContextId] = $this->createApplication();
		$itemId = $this->menu($contextId)[0]['id'];

		foreach ([
			'update' => fn () => $this->menuService->update($otherContextId, $itemId, 'Home', null, null, null, self::OWNER),
			'delete' => fn () => $this->menuService->delete($otherContextId, $itemId, self::OWNER),
		] as $case => $change) {
			try {
				$change();
				$this->fail($case . ' reached an item of another application.');
			} catch (NotFoundError) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testAMenuGoesThroughASchemeExportAndImport(): void {
		[$contextId, $startPageId, $pageId, $nodeRelId] = $this->createApplication();
		$group = $this->menuService->create($contextId, 'Links', 'star', 'Elsewhere', null, self::OWNER);
		$site = $this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER);
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['nodeRelId' => $nodeRelId]), self::OWNER);
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$menu = $this->menu($contextId);
		$this->menuService->saveOrder($contextId, MenuOrderEntry::listFromArray(array_map(static fn (array $item): array => ['id' => $item['id'], 'parentId' => $item['id'] === $site['id'] ? $group['id'] : null], $menu)), $this->version($contextId), self::OWNER);
		$exported = $this->menuService->export($this->contextService->findById($contextId, self::OWNER));

		$imported = $this->contextService->create('Imported', 'tables', '', [['id' => self::TABLE, 'type' => Application::NODE_TYPE_TABLE]], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $imported->getId();
		$importedContext = $this->contextService->findById($imported->getId(), self::OWNER);
		$pageIds = $this->menuService->schemePageIds($importedContext, $this->contextService->findById($contextId, self::OWNER)->getPages());
		$menu = $this->menuService->withLocalPages($exported, $pageIds);
		$this->contextService->importScheme($imported->getId(), 'Imported', 'tables', '', [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]], ['addTables' => [], 'modifyTables' => []], self::OWNER, $menu);

		$shape = static fn (array $items): array => array_map(static fn (array $item): array => [$item['displayTitle'], $item['parent'], $item['target'], $item['icon']], $items);
		$expected = $shape($exported);
		unset($expected[4]);
		$expected[0][0] = 'Imported';
		$expected[0][2] = ['page' => $pageIds[$startPageId]];
		$this->assertSame(array_values($expected), $shape($this->menuService->export($this->contextService->findById($imported->getId(), self::OWNER))));
		$this->assertSame([$startPageId, 1], [$exported[0]['target']['page'], $exported[2]['parent']]);
	}

	public function testAnExportLoadsTheTablesAndViewsOfItsItemsOnce(): void {
		[$contextId, , , $nodeRelId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['nodeRelId' => $nodeRelId]), self::OWNER);
		$table = new Table();
		$table->id = self::TABLE;
		$table->setUuid(self::TABLE_UUID);
		$table->setTitle('Projects');
		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->expects($this->once())->method('findMany')->with([self::TABLE])->willReturn([self::TABLE => $table]);
		$tableMapper->expects($this->never())->method('findIdToTitleMap');
		$viewMapper = $this->createMock(ViewMapper::class);
		$viewMapper->expects($this->once())->method('findMany')->with([])->willReturn([]);
		$viewMapper->expects($this->never())->method('findIdToTitleMap');
		$menuService = new MenuService($this->contextMapper, new ContextNodeRelationMapper($this->connectionAdapter), $this->menuItemMapper, $this->permissionsService, $tableMapper, $viewMapper, $this->connectionAdapter);

		$exported = $menuService->export($this->contextService->findById($contextId, self::OWNER));

		$this->assertSame(['Sales', 'Projects'], array_column($exported, 'displayTitle'));
		$this->assertSame(['table' => self::TABLE_UUID], $exported[1]['target']);
	}

	public function testAReadersExportLeavesOutTheEmptyGroupsTheReaderDoesNotSee(): void {
		[$contextId] = $this->createApplication();
		$this->menuService->create($contextId, 'Empty', null, null, null, self::OWNER);
		$context = $this->contextService->findById($contextId, self::OWNER);
		$this->canManage = false;

		$this->assertSame(['Sales'], array_column($this->menuService->export($context, 'reader'), 'displayTitle'));
		$this->assertSame(['Sales', 'Empty'], array_column($this->menuService->export($context), 'displayTitle'));
	}

	public function testAMovedUsersMenuKeepsItsTableItsPagesAndItsLinks(): void {
		[$contextId, , $pageId, $nodeRelId] = $this->createApplication();
		$this->menuService->create($contextId, 'Projects', null, null, MenuTarget::fromArray(['nodeRelId' => $nodeRelId]), self::OWNER);
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['url' => 'https://nextcloud.com']), self::OWNER);
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$context = $this->contextService->findById($contextId, self::OWNER);
		$exported = json_decode(json_encode($context->jsonSerialize() + ['pages' => $this->contextService->getPagesWithWidgets($context), 'menu' => $this->menuService->export($context)]), true);
		$movedTable = new Table();
		$movedTable->id = self::TABLE;
		$movedTable->setOwnership(self::OWNER);
		$exported['menu'][] = ['title' => 'Elsewhere', 'target' => ['table' => 'f0000000-0000-4000-8000-000000000000']];

		$moved = $this->contextService->importContext($movedTable, $exported, self::TABLE, self::TABLE_UUID);
		$this->contextIds[] = $moved->getId();

		$menu = $this->menu($moved->getId());
		$movedContext = $this->contextService->findById($moved->getId(), self::OWNER);
		$movedPages = array_flip(array_map(static fn (array $page): string => $page['page_type'], $movedContext->getPages()));
		$this->assertSame(['Sales', 'Projects', 'https://nextcloud.com', 'Overview'], array_column($menu, 'displayTitle'));
		$this->assertSame([['pageId' => $movedPages[Page::TYPE_STARTPAGE]], ['nodeRelId' => (int)array_key_first($movedContext->getNodes())], ['pageId' => $movedPages[Page::TYPE_GRID]]], [$menu[0]['target'], $menu[1]['target'], $menu[3]['target']]);
	}

	public function testASchemeImportWithoutAMenuKeepsTheMenu(): void {
		[$contextId, , $pageId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$before = $this->menu($contextId);

		$this->contextService->importScheme($contextId, 'Sales', 'tables', '', [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]], ['addTables' => [], 'modifyTables' => []], self::OWNER);

		$this->assertSame($before, $this->menu($contextId));
	}

	public function testASchemeImportLeavesOutAnItemForATableOutsideTheApplication(): void {
		[$contextId] = $this->createApplication();
		$menu = [
			['title' => 'Elsewhere', 'target' => ['table' => self::OTHER_TABLE_UUID]],
			['title' => 'Projects', 'target' => ['table' => self::TABLE_UUID]],
		];

		$this->contextService->importScheme($contextId, 'Sales', 'tables', '', [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]], ['addTables' => [], 'modifyTables' => []], self::OWNER, $menu);

		$this->assertSame(['Projects'], array_column($this->menu($contextId), 'displayTitle'));
	}

	public function testASchemeImportFindsAViewAmongTheApplicationsOwnViews(): void {
		$view = new View();
		$view->id = self::VIEW;
		$view->setUuid(self::VIEW_UUID);
		$view->setTableId(self::TABLE);
		$this->viewMapper->method('findByUuid')->willReturnCallback(static fn (string $uuid): View => $uuid === self::VIEW_UUID ? $view : throw new MultipleObjectsReturnedException('Two views'));
		$this->viewMapper->method('findMany')->willReturnCallback(static fn (array $ids): array => in_array(self::VIEW, $ids, true) ? [self::VIEW => $view] : []);
		$this->viewMapper->method('findIdToTitleMap')->willReturnCallback(static fn (array $ids): array => in_array(self::VIEW, $ids, true) ? [self::VIEW => 'Open projects'] : []);
		$context = $this->contextService->create('Sales', 'tables', '', [['id' => self::TABLE, 'type' => Application::NODE_TYPE_TABLE], ['id' => self::VIEW, 'type' => Application::NODE_TYPE_VIEW]], self::OWNER, Application::OWNER_TYPE_USER);
		$contextId = $context->getId();
		$this->contextIds[] = $contextId;
		$nodes = [
			['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1],
			['node_type' => Application::NODE_TYPE_VIEW, 'node_uuid' => self::VIEW_UUID, 'permissions' => 1],
		];
		$menu = [
			['title' => 'Shared', 'target' => ['view' => self::SHARED_VIEW_UUID]],
			['title' => null, 'target' => ['view' => self::VIEW_UUID]],
		];

		$this->contextService->importScheme($contextId, 'Sales', 'tables', '', $nodes, ['addTables' => [], 'modifyTables' => []], self::OWNER, $menu);

		$imported = $this->menu($contextId);
		$viewRelations = array_keys(array_filter($this->contextService->findById($contextId, self::OWNER)->getNodes(), static fn (array $node): bool => $node['node_type'] === Application::NODE_TYPE_VIEW));
		$this->assertSame([['Open projects', ['nodeRelId' => $viewRelations[0]]]], array_map(static fn (array $item): array => [$item['displayTitle'], $item['target']], $imported));
	}

	public function testTheMenuOfASchemePreviewImportsAsShown(): void {
		[$contextId, $startPageId, $pageId, $nodeRelId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['nodeRelId' => $nodeRelId]), self::OWNER);
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$context = $this->contextService->findById($contextId, self::OWNER);
		$nodes = [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]];
		$scheme = ['pages' => $this->contextService->getPagesWithWidgets($context), 'menu' => $this->menuService->export($context)];
		foreach ($this->menu($contextId) as $item) {
			$this->menuService->delete($contextId, $item['id'], self::OWNER);
		}

		$preview = $this->menuService->previewSchemeImport($this->contextService->findById($contextId, self::OWNER), $scheme['menu'], $scheme['pages'], $nodes);
		$this->contextService->importScheme($contextId, 'Sales', 'tables', '', $nodes, ['addTables' => [], 'modifyTables' => []], self::OWNER, $preview['to']);

		$this->assertSame([[], ['pageId' => $startPageId], ['nodeRelId' => $nodeRelId], ['pageId' => $pageId]], [$preview['skipped'], ...array_column($this->menu($contextId), 'target')]);
	}

	public function testAPreviewedItemStaysInItsGroupWhenAnItemBeforeItIsSkipped(): void {
		[$contextId, $startPageId] = $this->createApplication();
		$context = $this->contextService->findById($contextId, self::OWNER);
		$nodes = [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]];
		$menu = [
			['title' => null, 'target' => ['page' => $startPageId]],
			['title' => 'Projects', 'target' => ['table' => self::TABLE_UUID]],
			['title' => 'Gone', 'target' => ['page' => 999999999]],
			['title' => 'Group One', 'target' => null],
			['title' => 'Group Two', 'target' => null],
			['title' => 'Site', 'parent' => 3, 'target' => ['url' => 'https://nextcloud.com']],
		];

		$preview = $this->menuService->previewSchemeImport($context, $menu, $this->contextService->getPagesWithWidgets($context), $nodes);
		$this->contextService->importScheme($contextId, 'Sales', 'tables', '', $nodes, ['addTables' => [], 'modifyTables' => []], self::OWNER, $preview['to']);

		$imported = $this->menu($contextId);
		$this->assertSame([null, null, null, null, 2], array_column($preview['to'], 'parent'));
		$this->assertSame(['Sales', 'Projects', 'Group One', 'Group Two', 'Site'], array_column($imported, 'displayTitle'));
		$this->assertSame($imported[2]['id'], $imported[4]['parentId']);
	}

	public function testAnItemFollowsItsPageWhenTheImportDropsThePagesName(): void {
		[$contextId, , $pageId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$context = $this->contextService->findById($contextId, self::OWNER);
		$exported = json_decode(json_encode($context->jsonSerialize() + ['pages' => $this->contextService->getPagesWithWidgets($context), 'menu' => $this->menuService->export($context)]), true);
		$table = new Table();
		$table->id = self::TABLE;
		$table->setOwnership(self::OWNER);

		$moved = $this->contextService->importContext($table, $exported, self::TABLE, self::TABLE_UUID);
		$this->contextIds[] = $moved->getId();

		$movedContext = $this->contextService->findById($moved->getId(), self::OWNER);
		$gridPages = array_filter($movedContext->getPages(), static fn (array $page): bool => $page['page_type'] === Page::TYPE_GRID);
		$this->assertCount(1, $gridPages);
		$this->assertNotSame($context->getPages()[$pageId]['slug'], reset($gridPages)['slug']);
		$this->assertSame(['pageId' => (int)array_key_first($gridPages)], $this->menu($moved->getId())[1]['target']);
	}

	public function testSchemePagesAreMatchedByStartPageAndSlug(): void {
		[$contextId, $startPageId, $pageId] = $this->createApplication();
		$context = $this->contextService->findById($contextId, self::OWNER);
		$name = $context->getPages()[$pageId]['slug'];

		$this->assertSame([11 => $startPageId, 12 => $pageId], $this->menuService->schemePageIds($context, [
			['id' => 11, 'page_type' => Page::TYPE_STARTPAGE, 'slug' => 'home'],
			['id' => 12, 'page_type' => Page::TYPE_GRID, 'slug' => $name],
			['id' => 13, 'page_type' => Page::TYPE_GRID, 'slug' => 'somewhere_else'],
			['id' => 14, 'page_type' => Page::TYPE_GRID, 'slug' => $context->getPages()[$startPageId]['slug']],
			['page_type' => Page::TYPE_GRID, 'slug' => $name],
			'not a page',
		]));
	}

	public static function unusableItems(): array {
		$link = ['title' => 'Site', 'target' => ['url' => 'https://nextcloud.com']];
		return [
			'not an object' => [['Reports'], 'It is not an object.'],
			'a page that did not come along' => [[['target' => ['page' => 999999999]]], 'The application has no page 999999999.'],
			'a page the application does not have' => [[['target' => ['pageId' => 999999999]]], 'The target of a menu item is {pageId} of a page or {nodeRelId} of a table or view of this application, or {url} of a link.'],
			'a page by name' => [[['target' => ['page' => 'dashboard']]], 'Its target is not'],
			'a table that did not come along' => [[['target' => ['table' => 'f0000000-0000-4000-8000-000000000000']]], 'is not part of the application'],
			'an unknown kind of target' => [[['title' => 'Row', 'target' => ['row' => 'x']]], 'Its target is not'],
			'a link that is not http' => [[['target' => ['url' => 'ftp://nextcloud.com']]], 'A link is an http or https address'],
			'a group without a title' => [[['target' => null]], 'A group needs a title.'],
			'a title that is not a text' => [[['title' => ['Site'], 'target' => ['url' => 'https://nextcloud.com']]], 'Its title is not a text.'],
			'two targets' => [[['target' => ['url' => 'https://nextcloud.com', 'page' => 'dashboard']]], 'Its target is not'],
			'one item past the limit' => [array_fill(0, MenuItem::MAX_ITEMS_PER_APPLICATION + 1, $link), 'A menu holds at most 200 items.'],
		];
	}

	/**
	 * @dataProvider unusableItems
	 */
	public function testAnImportSkipsTheItemsItCannotUseWithTheReason(array $exported, string $reason): void {
		[$contextId] = $this->createApplication();
		$before = $this->menu($contextId);

		$skipped = $this->menuService->import($this->contextService->findById($contextId, self::OWNER), $exported, static fn (): ?int => null, static fn (): ?int => null);

		$this->assertCount(1, $skipped);
		$this->assertStringContainsString($reason, $skipped[0]['reason']);
		$this->assertCount(count($exported) === 1 ? count($before) : count($exported) - 1, $this->menu($contextId));
	}

	public function testAnImportReadsNoMoreEntriesThanAMenuHolds(): void {
		[$contextId] = $this->createApplication();
		$lookups = 0;
		$exported = array_fill(0, 1000, ['title' => 'Elsewhere', 'target' => ['table' => self::OTHER_TABLE_UUID]]);

		$skipped = $this->menuService->import($this->contextService->findById($contextId, self::OWNER), $exported, static function () use (&$lookups): ?int {
			$lookups++;
			return null;
		}, static fn (): ?int => null);

		$this->assertSame(MenuItem::MAX_ITEMS_PER_APPLICATION, $lookups);
		$this->assertCount(MenuItem::MAX_ITEMS_PER_APPLICATION + 1, $skipped);
		$this->assertSame(['index' => 200, 'title' => null, 'reason' => 'A menu holds at most 200 items. 800 more were left out.'], end($skipped));
	}

	public function testAnImportedItemLosesOnlyTheFieldsThisInstanceCannotUse(): void {
		[$contextId] = $this->createApplication();
		$exported = [
			['title' => str_repeat('g', 250), 'icon' => 'no-such-icon', 'target' => null],
			['title' => 'Site', 'parent' => 0, 'description' => str_repeat('d', 2500), 'target' => ['url' => 'https://nextcloud.com']],
			['title' => str_repeat('t', 250), 'parent' => 0, 'icon' => 'star', 'target' => ['url' => 'https://docs.nextcloud.com']],
		];

		$skipped = $this->menuService->import($this->contextService->findById($contextId, self::OWNER), $exported, static fn (): ?int => null, static fn (): ?int => null);

		$menu = $this->menu($contextId);
		$this->assertSame([], $skipped);
		$this->assertSame([str_repeat('g', 200), 'Site', null], array_column($menu, 'title'));
		$this->assertSame([null, null, 'star'], array_column($menu, 'icon'));
		$this->assertSame([null, null, null], array_column($menu, 'description'));
		$this->assertSame([null, $menu[0]['id'], $menu[0]['id']], array_column($menu, 'parentId'));
	}

	public static function menusWithoutAUsableItem(): array {
		return [
			'an empty menu' => [[], 0],
			'only unusable items' => [[['target' => ['url' => 'ftp://nextcloud.com']], ['title' => 'Elsewhere', 'target' => ['table' => self::OTHER_TABLE_UUID]]], 2],
		];
	}

	/**
	 * @dataProvider menusWithoutAUsableItem
	 */
	public function testAnImportThatKeepsNoItemLeavesTheMenuAsItIs(array $exported, int $skippedCount): void {
		[$contextId, , $pageId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, MenuTarget::fromArray(['pageId' => $pageId]), self::OWNER);
		$before = $this->menu($contextId);

		$skipped = $this->menuService->import($this->contextService->findById($contextId, self::OWNER), $exported, static fn (): ?int => null, static fn (): ?int => null);

		$this->assertCount($skippedCount, $skipped);
		$this->assertSame($before, $this->menu($contextId));
	}

	public function testAnImportedItemMovesToTheTopWhenItsGroupDidNotComeFirst(): void {
		[$contextId] = $this->createApplication();
		$exported = [
			['title' => 'Site', 'parent' => 1, 'target' => ['url' => 'https://nextcloud.com']],
			['title' => 'Links', 'target' => null],
			['title' => 'Docs', 'parent' => 1, 'target' => ['url' => 'https://docs.nextcloud.com']],
			['title' => 'Help', 'parent' => 0, 'target' => ['url' => 'https://help.nextcloud.com']],
		];

		$this->menuService->import($this->contextService->findById($contextId, self::OWNER), $exported, static fn (): ?int => null, static fn (): ?int => null);

		$menu = $this->menu($contextId);
		$this->assertSame([null, null, $menu[1]['id'], null], array_column($menu, 'parentId'));
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function menu(int $contextId): array {
		return $this->menuService->findAll($contextId, self::OWNER)['menu'];
	}

	private function version(int $contextId): string {
		return $this->menuService->findAll($contextId, self::OWNER)['version'];
	}

	/**
	 * An application "Sales" with its start page, a grid page "Overview" and a table.
	 *
	 * @return array{int, int, int, int} ids of the application, start page, grid page and node relation
	 */
	private function createApplication(): array {
		$context = $this->contextService->create('Sales', 'tables', '', [['id' => self::TABLE, 'type' => Application::NODE_TYPE_TABLE]], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $context->getId();
		$page = $this->pageService->createGridPage($context->getId(), 'Overview', null, null, null);
		$context = $this->contextService->findById($context->getId(), self::OWNER);
		$startPages = array_filter($context->getPages(), static fn (array $page): bool => $page['page_type'] === Page::TYPE_STARTPAGE);
		return [$context->getId(), (int)array_key_first($startPages), $page['id'], (int)array_key_first($context->getNodes())];
	}
}
