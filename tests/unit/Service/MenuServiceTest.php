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
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Db\WidgetSourceMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\GridWidgetTypes;
use OCA\Tables\Service\MenuService;
use OCA\Tables\Service\PageService;
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
 * Runs against the database.
 */
class MenuServiceTest extends DatabaseTestCase {
	private const OWNER = 'menu-service-owner';
	private const TABLE = 4151;

	private MenuService $menuService;
	private ContextService $contextService;
	private PageService $pageService;
	private MenuItemMapper $menuItemMapper;
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
		$pageMapper = new PageMapper($this->connectionAdapter);
		$pageContentMapper = new PageContentMapper($this->connectionAdapter);
		$widgetService = new WidgetService(new WidgetMapper($this->connectionAdapter), new WidgetSourceMapper($this->connectionAdapter), $this->connectionAdapter);
		$this->menuItemMapper = new MenuItemMapper($this->connectionAdapter);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->permissionsService->method('canManageContext')->willReturnCallback(fn (): bool => $this->canManage);
		$tableMapper = $this->createMock(TableMapper::class);
		$tableMapper->method('findIdToTitleMap')->willReturnCallback(static fn (array $ids): array => in_array(self::TABLE, $ids, true) ? [self::TABLE => 'Projects'] : []);

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
			$this->createMock(ViewMapper::class),
			$widgetService,
			new GridWidgetTypes($l10n),
			$this->menuItemMapper,
		);
		$this->pageService = new PageService($contextMapper, $pageMapper, $pageContentMapper, $widgetService, new GridWidgetTypes($l10n), $this->connectionAdapter, $this->menuItemMapper);
		$this->menuService = new MenuService($contextMapper, $this->menuItemMapper, $this->permissionsService, $tableMapper, $this->createMock(ViewMapper::class), $this->connectionAdapter);
	}

	protected function tearDown(): void {
		foreach ($this->contextIds as $contextId) {
			$this->contextService->delete($contextId, self::OWNER);
		}
		parent::tearDown();
	}

	public function testANewApplicationOpensItsStartPage(): void {
		[$contextId, $startPageId] = $this->createApplication();

		$menu = $this->menuService->findAll($contextId, self::OWNER);

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

	public function testAnItemWithoutATitleShowsTheTitleOfWhatItOpens(): void {
		[$contextId, , $pageId, $nodeRelId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, ['pageId' => $pageId], self::OWNER);
		$this->menuService->create($contextId, null, null, null, ['nodeRelId' => $nodeRelId], self::OWNER);
		$this->menuService->create($contextId, null, 'star', 'Our site', ['url' => 'https://nextcloud.com'], self::OWNER);
		$this->menuService->create($contextId, 'Reports', null, null, null, self::OWNER);

		$menu = $this->menuService->findAll($contextId, self::OWNER);

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
			'an unknown icon' => ['Reports', null, 'unknown icon', 'no-such-icon'],
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
		$this->menuService->create($contextId, $title, $icon, null, $target, self::OWNER);
	}

	public function testAnItemOpensAtMostOneThing(): void {
		[$contextId, , $pageId] = $this->createApplication();
		$item = $this->menuService->create($contextId, null, null, null, ['url' => 'https://nextcloud.com'], self::OWNER);
		$stored = $this->menuItemMapper->findById($item['id']);
		$stored->setPageId($pageId);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('not more than one');
		(new \ReflectionMethod(MenuService::class, 'assertValid'))->invoke($this->menuService, $stored);
	}

	public function testOnlyAManagerSeesAnEmptyGroup(): void {
		[$contextId] = $this->createApplication();
		$this->menuService->create($contextId, 'Reports', null, null, null, self::OWNER);

		$this->assertCount(2, $this->menuService->findAll($contextId, self::OWNER));
		$this->canManage = false;
		$this->assertSame(['Sales'], array_column($this->menuService->findAll($contextId, self::OWNER), 'displayTitle'));
	}

	public function testAMenuHoldsAtMost200Items(): void {
		[$contextId] = $this->createApplication();
		for ($i = 1; $i < MenuItem::MAX_ITEMS_PER_APPLICATION; $i++) {
			$item = new MenuItem();
			$item->setContextId($contextId);
			$item->setOrder($i);
			$item->setTitle('Group ' . $i);
			$this->menuItemMapper->insert($item);
		}

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('at most 200 items');
		$this->menuService->create($contextId, 'One more', null, null, null, self::OWNER);
	}

	public function testAnUpdateKeepsWhatIsLeftOutAndATargetReplacesThePreviousOne(): void {
		[$contextId, , $pageId] = $this->createApplication();
		$item = $this->menuService->create($contextId, 'Site', 'star', 'Ours', ['url' => 'https://nextcloud.com'], self::OWNER);

		$updated = $this->menuService->update($contextId, $item['id'], '', null, '', ['pageId' => $pageId], self::OWNER);

		$this->assertSame([null, 'Overview', 'star', null, ['pageId' => $pageId]], [$updated['title'], $updated['displayTitle'], $updated['icon'], $updated['description'], $updated['target']]);
		$this->assertSame($updated, $this->menuService->findAll($contextId, self::OWNER)[1]);
	}

	public function testAGroupThatHoldsItemsCannotOpenSomething(): void {
		[$contextId] = $this->createApplication();
		$menu = $this->menuService->findAll($contextId, self::OWNER);
		$group = $this->menuService->create($contextId, 'Reports', null, null, null, self::OWNER);
		$this->menuService->saveOrder($contextId, [['id' => $group['id'], 'parentId' => null], ['id' => $menu[0]['id'], 'parentId' => $group['id']]], self::OWNER);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Move its items out first');
		$this->menuService->update($contextId, $group['id'], null, null, null, ['url' => 'https://nextcloud.com'], self::OWNER);
	}

	public function testTheOrderSavesPositionAndGroupsAndDeletingAGroupMovesItsItemsUp(): void {
		[$contextId] = $this->createApplication();
		$start = $this->menuService->findAll($contextId, self::OWNER)[0];
		$site = $this->menuService->create($contextId, null, null, null, ['url' => 'https://nextcloud.com'], self::OWNER);
		$group = $this->menuService->create($contextId, 'Links', null, null, null, self::OWNER);

		$saved = $this->menuService->saveOrder($contextId, [
			['id' => $group['id'], 'parentId' => null],
			['id' => $site['id'], 'parentId' => $group['id']],
			['id' => $start['id'], 'parentId' => null],
		], self::OWNER);
		$this->assertSame([[$group['id'], null], [$site['id'], $group['id']], [$start['id'], null]], array_map(static fn (array $item): array => [$item['id'], $item['parentId']], $saved));

		$this->menuService->delete($contextId, $group['id'], self::OWNER);
		$this->assertSame([[$site['id'], null], [$start['id'], null]], array_map(static fn (array $item): array => [$item['id'], $item['parentId']], $this->menuService->findAll($contextId, self::OWNER)));
	}

	public static function invalidOrders(): array {
		return [
			'an item left out' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, null]], BadRequestError::class, 'changed by another save'],
			'an item listed twice' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, null], [$start, null], [$start, null]], BadRequestError::class, 'listed twice'],
			'a parent that is not a group' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, $start], [$start, null]], BadRequestError::class, 'only sit in a group'],
			'an item before its group' => [static fn (int $start, int $group, int $site): array => [[$site, $group], [$group, null], [$start, null]], BadRequestError::class, 'only sit in a group'],
			'a group in a group' => [static fn (int $start, int $group, int $site): array => [[$start, null], [$group, $group], [$site, null]], BadRequestError::class, 'only sit in a group'],
			'an item of another application' => [static fn (int $start, int $group, int $site): array => [[$group, null], [$site, null], [-1, null]], NotFoundError::class, 'is not an item of application'],
		];
	}

	/**
	 * @dataProvider invalidOrders
	 * @param \Closure(int, int, int): list<array{int, ?int}> $order
	 * @param class-string<\Throwable> $error
	 */
	public function testAnInvalidOrderSavesNothing(\Closure $order, string $error, string $message): void {
		[$contextId] = $this->createApplication();
		$start = $this->menuService->findAll($contextId, self::OWNER)[0]['id'];
		$group = $this->menuService->create($contextId, 'Links', null, null, null, self::OWNER)['id'];
		$site = $this->menuService->create($contextId, null, null, null, ['url' => 'https://nextcloud.com'], self::OWNER)['id'];
		$before = $this->menuService->findAll($contextId, self::OWNER);

		try {
			$this->menuService->saveOrder($contextId, array_map(static fn (array $entry): array => ['id' => $entry[0], 'parentId' => $entry[1]], $order($start, $group, $site)), self::OWNER);
			$this->fail('The order was saved');
		} catch (\Exception $e) {
			$this->assertInstanceOf($error, $e);
			$this->assertStringContainsString($message, $e->getMessage());
		}
		$this->assertSame($before, $this->menuService->findAll($contextId, self::OWNER));
	}

	public function testAnItemGoesWithItsTargetAndAnItemWhoseTargetIsGoneIsLeftOut(): void {
		[$contextId, $startPageId, $pageId, $nodeRelId] = $this->createApplication();
		$this->menuService->create($contextId, null, null, null, ['pageId' => $pageId], self::OWNER);
		$this->menuService->create($contextId, null, null, null, ['nodeRelId' => $nodeRelId], self::OWNER);
		$dangling = new MenuItem();
		$dangling->setContextId($contextId);
		$dangling->setOrder(9);
		$dangling->setPageId(-1);
		$this->menuItemMapper->insert($dangling);

		$this->pageService->delete($contextId, $pageId);
		$this->contextService->removeNodeFromContextAndPages($nodeRelId);

		$this->assertSame([['pageId' => $startPageId]], array_column($this->menuService->findAll($contextId, self::OWNER), 'target'));
		$this->assertCount(2, $this->menuItemMapper->findByContextId($contextId));
		$this->contextService->delete($contextId, self::OWNER);
		$this->contextIds = [];
		$this->assertSame([], $this->menuItemMapper->findByContextId($contextId));
	}

	public static function changes(): array {
		return [
			'create' => [static fn (MenuService $service, int $contextId, int $itemId) => $service->create($contextId, 'Reports', null, null, null, self::OWNER)],
			'update' => [static fn (MenuService $service, int $contextId, int $itemId) => $service->update($contextId, $itemId, 'Home', null, null, null, self::OWNER)],
			'delete' => [static fn (MenuService $service, int $contextId, int $itemId) => $service->delete($contextId, $itemId, self::OWNER)],
			'order' => [static fn (MenuService $service, int $contextId, int $itemId) => $service->saveOrder($contextId, [['id' => $itemId, 'parentId' => null]], self::OWNER)],
		];
	}

	/**
	 * @dataProvider changes
	 */
	public function testOnlyAManagerChangesTheMenu(\Closure $change): void {
		[$contextId] = $this->createApplication();
		$itemId = $this->menuService->findAll($contextId, self::OWNER)[0]['id'];
		$this->canManage = false;

		$this->expectException(PermissionError::class);
		$change($this->menuService, $contextId, $itemId);
	}

	public function testAnItemIsNotFoundThroughAnotherApplication(): void {
		[$contextId] = $this->createApplication();
		[$otherContextId] = $this->createApplication();
		$itemId = $this->menuService->findAll($contextId, self::OWNER)[0]['id'];

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

	/**
	 * An application "Sales" with its start page, a grid page "Overview" and a table.
	 *
	 * @return array{int, int, int, int} ids of the application, start page, grid page and node relation
	 */
	private function createApplication(): array {
		$context = $this->contextService->create('Sales', 'tables', '', [['id' => self::TABLE, 'type' => Application::NODE_TYPE_TABLE]], self::OWNER, Application::OWNER_TYPE_USER);
		$this->contextIds[] = $context->getId();
		$page = $this->pageService->create($context->getId(), 'Overview', null, null, null);
		$context = $this->contextService->findById($context->getId(), self::OWNER);
		$startPages = array_filter($context->getPages(), static fn (array $page): bool => $page['page_type'] === Page::TYPE_STARTPAGE);
		return [$context->getId(), (int)array_key_first($startPages), $page['id'], (int)array_key_first($context->getNodes())];
	}
}
