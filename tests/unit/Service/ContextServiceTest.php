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
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Model\WidgetInput;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\DB\Exception;
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
	private bool $contextExists = true;
	/** @var list<string> the writes of a test in the order they ran */
	private array $calls = [];
	private MockObject $widgetService;
	private MockObject $dbConnection;
	private MockObject $menuItemMapper;
	private MockObject $menuService;
	private MockObject $logger;
	private MockObject $permissionsService;
	private MockObject $tableMapper;
	private MockObject $viewMapper;
	/** @var array<class-string, object> services replaced for a test, with the originals */
	private array $replacedServices = [];
	/** @var list<mixed> */
	private array $serviceArguments;

	protected function setUp(): void {
		parent::setUp();

		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->contextMapper->method('lock')->willReturnCallback(function (int $contextId): bool {
			$this->calls[] = 'lock ' . $contextId;
			return $this->contextExists;
		});
		$this->contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
		$this->tableMapper = $this->createMock(TableMapper::class);
		$this->viewMapper = $this->createMock(ViewMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);
		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->widgetService = $this->createMock(WidgetService::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$shareService = $this->createMock(ShareService::class);
		$navigationManager = $this->createMock(INavigationManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$this->menuItemMapper = $this->createMock(MenuItemMapper::class);
		$this->menuService = $this->createMock(MenuService::class);

		$this->serviceArguments = [
			$this->contextMapper,
			$this->contextNodeRelMapper,
			$this->pageMapper,
			$this->pageContentMapper,
			$this->logger,
			$this->permissionsService,
			$this->userManager,
			$this->auditLogService,
			$this->dbConnection,
			$shareService,
			false,
			$navigationManager,
			$urlGenerator,
			$this->tableMapper,
			$this->viewMapper,
			$this->widgetService,
			new GridWidgetTypes($l10n),
			$this->menuItemMapper,
			$this->menuService,
		];
		$this->service = new ContextService(...$this->serviceArguments);
	}

	protected function tearDown(): void {
		foreach ($this->replacedServices as $class => $original) {
			$this->registerService($class, $original);
		}
		parent::tearDown();
	}

	/**
	 * Lets `\OCP\Server::get()` hand out a mock for the rest of the test.
	 *
	 * @template T of object
	 * @param class-string<T> $class
	 * @return T&MockObject
	 */
	private function replaceService(string $class): MockObject {
		$this->replacedServices[$class] ??= \OCP\Server::get($class);
		$mock = $this->createMock($class);
		$this->registerService($class, $mock);
		self::assertSame($mock, \OCP\Server::get($class), 'The mock of ' . $class . ' does not reach \\OCP\\Server::get().');
		return $mock;
	}

	/**
	 * Registers a service where `\OCP\Server::get()` resolves it: the app container for
	 * an app class, the server otherwise.
	 */
	private function registerService(string $class, object $service): void {
		$container = \OC::$server->getAppContainerForService($class) ?? \OC::$server;
		$container->registerService($class, static fn (): object => $service);
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

	public function testAFailedOrderWriteFailsTheUpdateAndIsNotLogged(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([
			101 => ['id' => 101, 'node_type' => 0, 'node_id' => 11, 'permissions' => 1],
			102 => ['id' => 102, 'node_type' => 0, 'node_id' => 12, 'permissions' => 1],
		]);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => [
			501 => ['id' => 501, 'node_rel_id' => 101, 'order' => 10],
			502 => ['id' => 502, 'node_rel_id' => 102, 'order' => 20],
		]]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->pageContentMapper->method('findById')->willReturnCallback(fn (int $contentId): PageContent => $this->buildPageContent($contentId, 301, $contentId - 400, 10));
		$this->pageContentMapper->method('update')->willThrowException(new Exception('serialization failure'));
		$this->auditLogService->expects($this->never())->method('log');

		$this->expectException(Exception::class);
		$this->service->update(7, 'user-1', null, null, null, [
			['id' => 12, 'type' => 0, 'permissions' => 1],
			['id' => 11, 'type' => 0, 'permissions' => 1],
		]);
	}

	public static function addedNodePermissions(): array {
		return [
			'given' => [['id' => 13, 'type' => 0, 'permissions' => 5], 5],
			'omitted' => [['id' => 13, 'type' => 0], Application::PERMISSION_READ],
		];
	}

	/**
	 * @dataProvider addedNodePermissions
	 */
	public function testTheAuditEntryOfAnAddedNodeHasItsPermissions(array $node, int $expectedPermissions): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([]);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->contextNodeRelMapper->method('insert')->willReturnCallback(static function (ContextNodeRelation $relation): ContextNodeRelation {
			$relation->id = 103;
			return $relation;
		});
		$this->pageContentMapper->method('insert')->willReturnCallback(static function (PageContent $content): PageContent {
			$content->id = 503;
			return $content;
		});
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextUpdated, 'Tables application with ID %s was updated', [
				'contextId' => 7,
				'addedNodes' => [['nodeType' => 0, 'nodeId' => 13, 'permissions' => $expectedPermissions]],
				'removedNodes' => [],
				'updatedNodes' => [],
			]);

		$this->service->update(7, 'user-1', null, null, null, [$node]);
	}

	public function testUpdateGivesAnAddedNodeTheShapeOfTheLoadedNodesAndInsertsItAtItsPosition(): void {
		[$insertedOrders, $updatedOrders] = $this->expectNodeAddedToContext(10, [501]);

		$updatedContext = $this->service->update(7, 'user-1', null, null, null, [
			['id' => 12, 'type' => 1, 'permissions' => 3],
			['id' => 11, 'type' => 0, 'permissions' => 1],
		]);

		self::assertSame([10], $insertedOrders->getArrayCopy());
		self::assertSame([501 => 20], $updatedOrders->getArrayCopy());
		self::assertSame(['id' => 102, 'node_id' => 12, 'node_type' => 1, 'permissions' => 3], $updatedContext->getNodes()[102]);
		self::assertSame(['order' => 10, 'node_rel_id' => 102], $updatedContext->getPages()[301]['content'][502]);
		self::assertSame(20, $updatedContext->getPages()[301]['content'][501]['order']);
	}

	public function testUpdateStoresTheOrderSentForAddedAndKeptNodes(): void {
		[$insertedOrders, $updatedOrders] = $this->expectNodeAddedToContext(5, [501]);

		$updatedContext = $this->service->update(7, 'user-1', null, null, null, [
			['id' => 11, 'type' => 0, 'permissions' => 1, 'order' => 40],
			['id' => 12, 'type' => 1, 'permissions' => 3, 'order' => 5],
		]);

		self::assertSame([5], $insertedOrders->getArrayCopy());
		self::assertSame([501 => 40], $updatedOrders->getArrayCopy());
		self::assertSame(5, $updatedContext->getPages()[301]['content'][502]['order']);
		self::assertSame(40, $updatedContext->getPages()[301]['content'][501]['order']);
	}

	/**
	 * Prepares a context with node 11 (relation 101, content 501 at order 10) on its startpage,
	 * to which an update adds node 12 as relation 102 with content 502.
	 *
	 * @param int[] $updatedContentIds
	 * @return array{\ArrayObject<int, int>, \ArrayObject<int, int>} the inserted orders and the updated orders by content id
	 */
	private function expectNodeAddedToContext(int $insertedOrder, array $updatedContentIds): array {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([
			101 => ['id' => 101, 'node_type' => 0, 'node_id' => 11, 'permissions' => 1],
		]);
		$context->setPages([
			301 => [
				'id' => 301,
				'page_type' => Page::TYPE_STARTPAGE,
				'content' => [501 => ['order' => 10, 'node_rel_id' => 101]],
			],
		]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->permissionsService->method('canManageNodeById')->willReturn(true);
		$this->contextNodeRelMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (ContextNodeRelation $relation): ContextNodeRelation {
				$relation->id = 102;
				return $relation;
			});

		$insertedOrders = new \ArrayObject();
		$this->pageContentMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (PageContent $content) use ($insertedOrders): PageContent {
				$insertedOrders[] = $content->getOrder();
				$content->id = 502;
				return $content;
			});

		$pageContentsById = [
			501 => $this->buildPageContent(501, 301, 101, 10),
			502 => $this->buildPageContent(502, 301, 102, $insertedOrder),
		];
		$this->pageContentMapper->expects($this->exactly(count($updatedContentIds)))
			->method('findById')
			->willReturnCallback(static fn (int $contentId): PageContent => $pageContentsById[$contentId]);

		$updatedOrders = new \ArrayObject();
		$this->pageContentMapper->expects($this->exactly(count($updatedContentIds)))
			->method('update')
			->willReturnCallback(static function (PageContent $content) use ($updatedOrders): PageContent {
				$updatedOrders[$content->getId()] = $content->getOrder();
				return $content;
			});

		return [$insertedOrders, $updatedOrders];
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
		$nodeRelation->setContextId(7);
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

	public function testAnUpdateRefusedForAnAddedNodeRemovesNothing(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([101 => ['id' => 101, 'node_type' => 0, 'node_id' => 11, 'permissions' => 1]]);
		$context->setPages([]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->permissionsService->method('canManageNodeById')->willReturn(false);
		$this->widgetService->expects($this->never())->method('deleteSourcesByNodeRelIds');
		$this->contextNodeRelMapper->expects($this->never())->method('delete');
		$this->contextMapper->expects($this->never())->method('update');

		$this->expectException(PermissionError::class);
		$this->service->update(7, 'user-1', 'Renamed', null, null, [['id' => 12, 'type' => 0, 'permissions' => 1]]);
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

	public function testUpdateKeepsThePermissionsOfAKeptNodeWithoutPermissions(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([
			101 => ['id' => 101, 'node_type' => 0, 'node_id' => 11, 'permissions' => 7],
		]);
		$context->setPages([]);
		$this->contextMapper->method('findById')->with(7, 'user-1')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->contextNodeRelMapper->expects($this->never())->method('update');
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextUpdated, 'Tables application with ID %s was updated', [
				'contextId' => 7,
				'addedNodes' => [],
				'removedNodes' => [],
				'updatedNodes' => [],
			]);

		$updatedContext = $this->service->update(7, 'user-1', null, null, null, [
			['id' => 11, 'type' => 0],
		]);

		self::assertSame(7, $updatedContext->getNodes()[101]['permissions']);
	}

	public function testUpdateRevokesThePermissionsOfAKeptNodeGivenZero(): void {
		$context = new Context();
		$context->id = 7;
		$context->setNodes([
			101 => ['id' => 101, 'node_type' => 0, 'node_id' => 11, 'permissions' => 7],
		]);
		$context->setPages([]);
		$this->contextMapper->method('findById')->with(7, 'user-1')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$nodeRelation = new ContextNodeRelation();
		$nodeRelation->id = 101;
		$nodeRelation->setNodeType(0);
		$nodeRelation->setNodeId(11);
		$nodeRelation->setPermissions(7);
		$this->contextNodeRelMapper->method('findById')->with(101)->willReturn($nodeRelation);
		$this->contextNodeRelMapper->expects($this->once())->method('update')->with($nodeRelation)->willReturnArgument(0);
		$this->auditLogService->expects($this->once())
			->method('log')
			->with(Operation::ContextUpdated, 'Tables application with ID %s was updated', [
				'contextId' => 7,
				'addedNodes' => [],
				'removedNodes' => [],
				'updatedNodes' => [['nodeType' => 0, 'nodeId' => 11, 'previousPermissions' => 7, 'permissions' => 0]],
			]);

		$this->service->update(7, 'user-1', null, null, null, [
			['id' => 11, 'type' => 0, 'permissions' => 0],
		]);

		self::assertSame(0, $nodeRelation->getPermissions());
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

	public function testCreateGivesANodeWithoutPermissionsReadPermissions(): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 9;
			return $context;
		});
		$this->permissionsService->method('canManageNodeById')->with(0, 5, 'alice')->willReturn(true);
		$insertedRelation = null;
		$this->contextNodeRelMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(static function (ContextNodeRelation $relation) use (&$insertedRelation): ContextNodeRelation {
				$relation->id = 101;
				$insertedRelation = $relation;
				return $relation;
			});
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page): Page {
			$page->id = 1;
			return $page;
		});
		$this->pageContentMapper->method('insert')->willReturnCallback(static function (PageContent $pageContent): PageContent {
			$pageContent->id = 501;
			return $pageContent;
		});

		$this->service->create('Projects', 'icon', '', [['id' => 5, 'type' => 0]], 'alice', Application::OWNER_TYPE_USER);

		self::assertInstanceOf(ContextNodeRelation::class, $insertedRelation);
		self::assertSame(Application::PERMISSION_READ, $insertedRelation->getPermissions());
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

	public function testASecondDeleteOfTheSameApplicationIsNotFoundAndNotLogged(): void {
		$context = new Context();
		$context->id = 9;
		$this->contextMapper->method('findById')->with(9, 'alice')->willReturn($context);
		$this->contextExists = false;

		$this->contextMapper->expects($this->never())->method('delete');
		$this->auditLogService->expects($this->never())->method('log');
		$this->expectException(NotFoundError::class);

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

	public function testDeletingAnApplicationRemovesItsMenuLastBeforeTheApplication(): void {
		$context = new Context();
		$context->id = 7;
		$this->contextMapper->method('findById')->willReturn($context);
		$this->pageMapper->method('getPageIdsForContext')->willReturn([301]);
		$calls = $this->recordCalls([
			[$this->contextNodeRelMapper, 'deleteAllByContextId', 'relations'],
			[$this->pageMapper, 'deleteByPageId', 'pages'],
			[$this->menuItemMapper, 'deleteByContextId', 'menu items'],
			[$this->contextMapper, 'delete', 'application'],
		]);

		$this->service->delete(7, 'user-1');

		self::assertSame(['relations', 'pages', 'menu items', 'application'], $calls->getArrayCopy());
	}

	public static function nodeRemovals(): array {
		return [
			'a deleted table or view' => [static fn (ContextService $service) => $service->deleteNodeRel(11, 0)],
			'a node taken out of an application' => [static fn (ContextService $service) => $service->removeNodeFromContextAndPages(101)],
		];
	}

	/**
	 * @dataProvider nodeRemovals
	 */
	public function testANodeIsRemovedInOneOrder(\Closure $remove): void {
		$this->contextNodeRelMapper->method('getRelIdsForNode')->willReturn([101]);
		$relation = new ContextNodeRelation();
		$relation->setContextId(7);
		$this->contextNodeRelMapper->method('findById')->willReturn($relation);
		$calls = $this->recordCalls([
			[$this->widgetService, 'deleteSourcesByNodeRelIds', 'widget sources'],
			[$this->pageContentMapper, 'deleteByNodeRelIds', 'page contents'],
			[$this->menuItemMapper, 'deleteByNodeRelIds', 'menu items'],
			[$this->contextNodeRelMapper, 'deleteByNodeRelIds', 'relations'],
			[$this->contextNodeRelMapper, 'delete', 'relations'],
		]);

		$remove($this->service);

		self::assertSame(['widget sources', 'page contents', 'menu items', 'relations'], $calls->getArrayCopy());
	}

	public function testDeletingANodeUnlinksItsWidgets(): void {
		$this->contextNodeRelMapper->method('getRelIdsForNode')->with(11, 0)->willReturn([101, 102]);

		$this->widgetService->expects($this->once())->method('deleteSourcesByNodeRelIds')->with([101, 102]);
		$this->pageContentMapper->expects($this->once())->method('deleteByNodeRelIds')->with([101, 102]);

		$this->service->deleteNodeRel(11, 0);
	}

	public function testAFailedNodeRemovalRollsBackTheDeletedSources(): void {
		$nodeRelation = new ContextNodeRelation();
		$nodeRelation->id = 101;
		$nodeRelation->setContextId(7);
		$this->contextNodeRelMapper->method('findById')->willReturn($nodeRelation);
		$this->contextNodeRelMapper->method('delete')->willThrowException(new Exception('gone'));
		$this->widgetService->expects($this->once())->method('deleteSourcesByNodeRelIds')->with([101]);
		$this->dbConnection->expects($this->once())->method('rollBack');
		$this->dbConnection->expects($this->never())->method('commit');

		$this->expectException(Exception::class);
		$this->service->removeNodeFromContextAndPages(101);
	}

	public function testDeletingANodeLocksItsApplicationsInAscendingOrderFirst(): void {
		$this->contextNodeRelMapper->method('getContextIdsForNode')->with(11, 0)->willReturn([9, 3, 9]);
		$this->contextNodeRelMapper->method('getRelIdsForNode')->willReturnCallback(function (): array {
			$this->calls[] = 'read relations';
			return [101, 102];
		});
		$this->recordNodeRemoval();
		$this->contextNodeRelMapper->method('deleteByNodeRelIds')->willReturnCallback(function (): void {
			$this->calls[] = 'relations';
		});

		$this->service->deleteNodeRel(11, 0);

		$this->assertSame(['lock 3', 'lock 9', 'read relations', 'sources', 'contents', 'relations'], $this->calls);
	}

	public function testRemovingANodeFromAnApplicationLocksTheApplicationFirst(): void {
		$nodeRelation = new ContextNodeRelation();
		$nodeRelation->id = 101;
		$nodeRelation->setContextId(7);
		$this->contextNodeRelMapper->method('findById')->with(101)->willReturn($nodeRelation);
		$this->pageContentMapper->method('findByNodeRelation')->willReturn([]);
		$this->recordNodeRemoval();
		$this->contextNodeRelMapper->method('delete')->willReturnCallback(function (ContextNodeRelation $relation): ContextNodeRelation {
			$this->calls[] = 'relations';
			return $relation;
		});

		$this->service->removeNodeFromContextAndPages(101);

		$this->assertSame(['lock 7', 'sources', 'contents', 'relations'], $this->calls);
	}

	private function recordNodeRemoval(): void {
		$this->widgetService->method('deleteSourcesByNodeRelIds')->willReturnCallback(function (): void {
			$this->calls[] = 'sources';
		});
		$this->pageContentMapper->method('deleteByNodeRelIds')->willReturnCallback(function (): void {
			$this->calls[] = 'contents';
		});
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
		$this->widgetService->method('insertAll')->willReturnCallback(function (int $pageId, array $widgets) use (&$inserted): array {
			$inserted = [...$inserted, ...self::widgetArgs($pageId, $widgets)];
			return [];
		});

		$this->service->importContext($table, [
			'name' => 'Projects',
			'nodes' => [['id' => 101, 'node_id' => 5, 'node_type' => 0, 'permissions' => 1]],
			'pages' => [[
				'id' => 301,
				'page_type' => Page::TYPE_GRID,
				'slug' => 'overview',
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

		self::assertSame(['overview', 'Overview', 'star', 'At a glance'], [$insertedPages[0]->getSlug(), $insertedPages[0]->getTitle(), $insertedPages[0]->getIcon(), $insertedPages[0]->getDescription()]);
		self::assertCount(4, $inserted);
		self::assertSame(['Notes', ['gridX' => 6, 'gridY' => 11, 'gridWidth' => 6, 'gridHeight' => 2]], [$inserted[3][2], $inserted[3][3]]);
		self::assertSame([[600, 501, 10], [601, 501, 10]], $insertedContents, 'the archive has no start page, so a new one holds the table');
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
		self::assertSame('dashboard', $inserted[0]->getSlug());
		self::assertSame('dashboard', $context->getPages()[70]['slug']);
	}

	public static function importedPages(): array {
		$start = Page::TYPE_STARTPAGE;
		$grid = Page::TYPE_GRID;
		return [
			'free names are kept, the rest get the default' => [
				[
					['id' => 1, 'page_type' => $start],
					['id' => 2, 'page_type' => $grid, 'slug' => 'sales'],
					['id' => 3, 'page_type' => $grid, 'slug' => 'sales'],
					['id' => 4, 'page_type' => $grid, 'slug' => 'Bad Name'],
					['id' => 5, 'page_type' => $grid],
					['id' => 6, 'page_type' => $grid, 'slug' => 'page_2'],
				],
				[80 => [$start, 'dashboard'], 81 => [$grid, 'sales'], 82 => [$grid, 'page_82'], 83 => [$grid, 'page_83'], 84 => [$grid, 'page_84'], 85 => [$grid, 'page_85']],
			],
			'the start page leaves dashboard to the page exported with it' => [
				[['id' => 1, 'page_type' => $start], ['id' => 2, 'page_type' => $grid, 'slug' => 'dashboard']],
				[80 => [$start, 'page_80'], 81 => [$grid, 'dashboard']],
			],
			'only one start page and grid pages are imported' => [
				[
					['id' => 1, 'page_type' => $start, 'slug' => 'home'],
					['id' => 2, 'page_type' => $start],
					['id' => 3, 'page_type' => 'chart'],
					['id' => 4],
					'not a page',
					['id' => 5, 'page_type' => $grid],
				],
				[80 => [$start, 'home'], 81 => [$grid, 'page_81']],
			],
			'an application without a start page gets one' => [
				[['id' => 2, 'page_type' => $grid, 'slug' => 'sales', 'title' => 'Sales']],
				[80 => [$grid, 'sales'], 81 => [$start, 'dashboard']],
			],
			'the new start page leaves dashboard to an imported page' => [
				[['id' => 2, 'page_type' => $grid, 'slug' => 'dashboard']],
				[80 => [$grid, 'dashboard'], 81 => [$start, 'page_81']],
			],
			'no pages at all' => [
				[],
				[80 => [$start, 'dashboard']],
			],
		];
	}

	/**
	 * @dataProvider importedPages
	 * @param array<int, array{0: string, 1: string}> $expected type and slug by page id
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
			$inserted[$page->getId()] = [$page->getPageType(), $page->getSlug()];
			return $page;
		});
		$this->pageMapper->method('insertWithDefaultName')->willReturnCallback(static function (Page $page) use (&$inserted, &$pageId): Page {
			$page->id = $pageId++;
			$page->setSlug('page_' . $page->getId());
			$inserted[$page->getId()] = [$page->getPageType(), $page->getSlug()];
			return $page;
		});
		$titles = [];
		$this->pageMapper->method('update')->willReturnCallback(static function (Page $page) use (&$titles): Page {
			$titles[$page->getId()] = $page->getTitle();
			return $page;
		});

		$this->service->importContext($table, ['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => $pages], 4);

		self::assertSame($expected, $inserted);
		$titled = array_column(array_filter($pages, static fn ($page): bool => is_array($page) && isset($page['title'])), 'slug');
		$untitledGridPages = array_keys(array_filter($inserted, static fn (array $page): bool => $page[0] === Page::TYPE_GRID && !in_array($page[1], $titled, true)));
		self::assertSame(array_map(static fn (int $id): string => $inserted[$id][1], $untitledGridPages), array_values($titles), 'every grid page without a title is named after its slug');
	}

	public function testImportKeepsTheTitleIconAndDescriptionOfAPage(): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 9;
			return $context;
		});
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$inserted = [];
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use (&$inserted): Page {
			$page->id = 80 + count($inserted);
			$inserted[] = $page;
			return $page;
		});
		$this->pageMapper->expects($this->never())->method('update');

		$this->service->importContext($this->importTable(), ['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => [
			['id' => 1, 'page_type' => Page::TYPE_STARTPAGE, 'title' => 'Welcome', 'icon' => 'star', 'description' => 'Start here'],
			['id' => 2, 'page_type' => Page::TYPE_GRID, 'slug' => 'overview', 'title' => '  Overview ', 'icon' => 'no-such-icon', 'description' => str_repeat('a', 2001)],
		]], 4);

		self::assertSame(
			[['Welcome', 'star', 'Start here'], ['Overview', null, null]],
			array_map(static fn (Page $page): array => [$page->getTitle(), $page->getIcon(), $page->getDescription()], $inserted),
		);
	}

	public static function importedStartPages(): array {
		return [
			'an exported start page' => [[['id' => 1, 'page_type' => Page::TYPE_GRID, 'title' => 'Overview'], ['id' => 2, 'page_type' => Page::TYPE_STARTPAGE]], 81],
			'a new start page' => [[['id' => 1, 'page_type' => Page::TYPE_GRID, 'title' => 'Overview']], 81],
		];
	}

	/**
	 * @dataProvider importedStartPages
	 */
	public function testAnImportedApplicationGetsAMenuThatOpensItsStartPage(array $pages, int $startPageId): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 9;
			return $context;
		});
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$pageId = 80;
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page) use (&$pageId): Page {
			$page->id = $pageId++;
			return $page;
		});
		$this->pageMapper->method('insertWithDefaultName')->willReturnCallback(static function (Page $page) use (&$pageId): Page {
			$page->id = $pageId++;
			return $page;
		});
		$this->menuItemMapper->expects($this->once())->method('insert')->with($this->callback(
			static fn (MenuItem $item): bool => [$item->getContextId(), $item->getOrder(), $item->getPageId(), $item->getTitle()] === [9, 0, $startPageId, null],
		))->willReturnArgument(0);

		$this->service->importContext($this->importTable(), ['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => $pages], 4);
	}

	public static function importedMenus(): array {
		return [
			'a menu of which an item is kept' => [true, 0],
			'a menu of which no item is kept' => [false, 1],
		];
	}

	/**
	 * @dataProvider importedMenus
	 */
	public function testAnImportedApplicationGetsTheStartPageItemOnlyWhenItsMenuKeepsNoItem(bool $kept, int $seeded): void {
		$this->contextMapper->method('insert')->willReturnCallback(static function (Context $context): Context {
			$context->id = 9;
			return $context;
		});
		$this->contextMapper->method('findById')->willReturn(new Context());
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$this->pageMapper->method('insert')->willReturnCallback(static function (Page $page): Page {
			$page->id = 80;
			return $page;
		});
		$menu = [['title' => 'Site', 'target' => ['url' => 'https://nextcloud.com']]];
		$this->menuService->expects($this->once())->method('import')->with($this->anything(), $menu)->willReturn([]);
		$this->menuItemMapper->method('findByContextId')->with(9)->willReturn($kept ? [new MenuItem()] : []);
		$this->menuItemMapper->expects($this->exactly($seeded))->method('insert')->with($this->callback(
			static fn (MenuItem $item): bool => [$item->getContextId(), $item->getPageId()] === [9, 80],
		))->willReturnArgument(0);

		$this->service->importContext($this->importTable(), ['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => [
			['id' => 1, 'page_type' => Page::TYPE_STARTPAGE, 'slug' => 'home'],
		], 'menu' => $menu], 4);
	}

	public function testAFailedImportIsRolledBack(): void {
		$this->contextMapper->method('insert')->willReturnArgument(0);
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$this->pageMapper->method('insert')->willThrowException(new \RuntimeException('database error'));
		$this->dbConnection->expects($this->once())->method('beginTransaction');
		$this->dbConnection->expects($this->once())->method('rollBack');
		$this->dbConnection->expects($this->never())->method('commit');

		$this->expectException(\RuntimeException::class);
		$this->service->importContext($this->importTable(), ['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => [
			['id' => 1, 'page_type' => Page::TYPE_STARTPAGE, 'slug' => 'home'],
		]], 4);
	}

	/**
	 * Records the name of each call, in call order.
	 *
	 * @param list<array{MockObject, string, string}> $methods mock, method and name
	 */
	private function recordCalls(array $methods): \ArrayObject {
		$calls = new \ArrayObject();
		foreach ($methods as [$mock, $method, $name]) {
			$mock->method($method)->willReturnCallback(static function (mixed $argument = null) use ($calls, $name): mixed {
				$calls[] = $name;
				return $argument;
			});
		}
		return $calls;
	}

	private function importTable(): Table {
		$table = new Table();
		$table->id = 40;
		$table->setOwnership('user-1');
		return $table;
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
		$this->widgetService->method('insertAll')->willReturnCallback(static function (int $pageId, array $widgets) use ($record): array {
			$record['widgets'] = [...$record['widgets'], ...self::widgetArgs($pageId, $widgets)];
			return [];
		});
		return $record;
	}

	/**
	 * The widgets handed to insertAll(), each as page id, type, title, position, settings,
	 * source, icon and description.
	 *
	 * @param list<WidgetInput> $widgets
	 * @return list<list<mixed>>
	 */
	private static function widgetArgs(int $pageId, array $widgets): array {
		return array_map(static fn (WidgetInput $w): array => [$pageId, $w->type, $w->title, ['gridX' => $w->gridX, 'gridY' => $w->gridY, 'gridWidth' => $w->gridWidth, 'gridHeight' => $w->gridHeight], $w->settings, $w->nodeRelId, $w->icon, $w->description], $widgets);
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
				'slug' => 'overview',
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
				'slug' => 'overview',
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

		self::assertSame(['overview', null], [$record['pages'][0]->getTitle(), $record['pages'][0]->getDescription()], 'an unusable title falls back to the slug');
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
			'pages' => [['id' => 301, 'page_type' => Page::TYPE_GRID, 'slug' => 'one', 'content' => [], 'widgets' => $widgets]],
		], 5);

		self::assertCount(GridWidgetTypes::MAX_WIDGETS_PER_PAGE, $record['widgets']);
	}

	private const TABLE_UUID = '0195f1a0-0000-7000-8000-000000000001';
	private const OTHER_TABLE_UUID = '0195f1a0-0000-7000-8000-000000000002';
	private const VIEW_UUID = '0195f1a0-0000-7000-8000-000000000003';
	private const NEW_TABLE_UUID = '0195f1a0-0000-7000-8000-000000000004';
	private const NEW_VIEW_UUID = '0195f1a0-0000-7000-8000-000000000005';
	private const UNAVAILABLE = 'Node 0 of the scheme is not a table or view that is here and can be managed by the user.';

	/**
	 * @return array<string, array{mixed, ?int}>
	 */
	public static function tablesThatCannotBeModified(): array {
		return [
			'not here' => [self::TABLE_UUID, null],
			'here but cannot be managed' => [self::TABLE_UUID, 5],
			'a uuid that is not a string' => [123, 5],
			'a string that is not a uuid' => ['some-uuid', 5],
		];
	}

	/**
	 * @dataProvider tablesThatCannotBeModified
	 */
	public function testImportingASchemeRefusesToModifyATableThatIsNotHereOrCannotBeManagedBeforeWriting(mixed $uuid, ?int $tableId): void {
		$this->tableMapper->method('findAllByUuids')->willReturn($tableId === null ? [] : [$this->table($tableId, self::TABLE_UUID)]);
		$this->permissionsService->method('canManageTable')->willReturn(false);
		$tableService = $this->replaceService(TableService::class);
		$tableService->expects($this->never())->method('importTable');
		$tableService->expects($this->never())->method('updateTableStructure');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('The table ' . (is_string($uuid) ? $uuid : 'at position 0') . ' to modify is not here or cannot be managed by the user.');
		$this->service->importScheme(7, 'Intake', 'star', '', [], [
			'addTables' => [$this->tableScheme(self::NEW_TABLE_UUID)],
			'modifyTables' => [$this->tableScheme($uuid)],
		], 'user-1');
	}

	public function testImportingASchemeModifiesATableTheUserCanManage(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithNodes([]));
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->tableMapper->method('findAllByUuids')->willReturn([$this->table(5, self::TABLE_UUID), $this->table(6, self::OTHER_TABLE_UUID)]);
		$this->permissionsService->method('canManageTable')->willReturnCallback(static fn (Table $table): bool => $table->getId() === 5);
		$tableService = $this->replaceService(TableService::class);

		$tableService->expects($this->once())->method('updateTableStructure')->with(5);
		$this->service->importScheme(7, 'Intake', 'star', '', [], ['addTables' => [], 'modifyTables' => [$this->tableScheme(self::TABLE_UUID)]], 'user-1');

		$this->expectException(BadRequestError::class);
		$this->service->importScheme(7, 'Intake', 'star', '', [], ['addTables' => [], 'modifyTables' => [$this->tableScheme(self::OTHER_TABLE_UUID)]], 'user-1');
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function nodesThatCannotBeImported(): array {
		return [
			'a table that is not here' => [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::OTHER_TABLE_UUID, 'permissions' => 1]],
			'a table that cannot be managed' => [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]],
			'a view that cannot be managed' => [['node_type' => Application::NODE_TYPE_VIEW, 'node_uuid' => self::VIEW_UUID, 'permissions' => 1]],
			'a uuid that is not a string' => [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => 123, 'permissions' => 1]],
			'a string that is not a uuid' => [['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => 'some-uuid', 'permissions' => 1]],
			'no uuid' => [['node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1]],
			'a node type that is a string' => [['node_type' => '0', 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]],
			'not an object' => ['table'],
		];
	}

	/**
	 * @dataProvider nodesThatCannotBeImported
	 */
	public function testImportingASchemeRefusesANodeThatCannotBeImportedAlikeBeforeWriting(mixed $node): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithNodes([]));
		$this->tableMapper->method('findAllByUuids')->willReturnCallback(fn (array $uuids): array => in_array(self::TABLE_UUID, $uuids, true) ? [$this->table(5, self::TABLE_UUID)] : []);
		$this->viewMapper->method('findAllByUuids')->willReturnCallback(fn (array $uuids): array => in_array(self::VIEW_UUID, $uuids, true) ? [$this->view(6, 5, self::VIEW_UUID)] : []);
		$this->permissionsService->method('canManageTable')->willReturn(false);
		$this->permissionsService->method('canManageView')->willReturn(false);
		$tableService = $this->replaceService(TableService::class);
		$tableService->expects($this->never())->method('importTable');
		$this->contextNodeRelMapper->expects($this->never())->method('insert');

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage(self::UNAVAILABLE);
		$this->service->importScheme(7, 'Intake', 'star', '', [$node], ['addTables' => [$this->tableScheme(self::NEW_TABLE_UUID)], 'modifyTables' => []], 'user-1');
	}

	public function testImportingASchemeLooksUpEachUuidOnceAndCountsTheNodesItRefuses(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithNodes([]));
		$this->tableMapper->expects($this->once())->method('findAllByUuids')->with([self::TABLE_UUID, self::OTHER_TABLE_UUID])->willReturn([]);
		$this->viewMapper->expects($this->never())->method('findAllByUuids');
		$node = static fn (string $uuid): array => ['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => $uuid, 'permissions' => 1];

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage(self::UNAVAILABLE . ' 2 more nodes cannot be imported either.');
		$this->service->importScheme(7, 'Intake', 'star', '', [$node(self::TABLE_UUID), $node(self::OTHER_TABLE_UUID), $node(self::TABLE_UUID)], ['addTables' => [], 'modifyTables' => []], 'user-1');
	}

	public function testImportingASchemeRefusesMoreNodesThanItTakesBeforeLookingThemUp(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithNodes([]));
		$this->tableMapper->expects($this->never())->method('findAllByUuids');
		$nodes = array_fill(0, ContextService::MAX_SCHEME_NODES + 1, ['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1]);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('A scheme has at most ' . ContextService::MAX_SCHEME_NODES . ' nodes.');
		$this->service->importScheme(7, 'Intake', 'star', '', $nodes, ['addTables' => [], 'modifyTables' => []], 'user-1');
	}

	public function testImportingASchemeKeepsACurrentNodeAndAddsManageableAndImportedOnes(): void {
		// table 3 is a node of the application the user cannot manage, table 4 and view 9 can be managed
		$before = $this->contextWithNodes([101 => ['id' => 101, 'node_id' => 3, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1]]);
		$after = $this->contextWithNodes([
			101 => ['id' => 101, 'node_id' => 3, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
			202 => ['id' => 202, 'node_id' => 20, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
			203 => ['id' => 203, 'node_id' => 21, 'node_type' => Application::NODE_TYPE_VIEW, 'permissions' => 1],
		]);
		// the check reads the application without its other pages, the menu import reads it after the update
		$this->contextMapper->method('findById')->willReturnCallback(static fn (int $id, ?string $userId = null, bool $withAllPages = true): Context => $withAllPages ? $after : $before);
		$this->tableMapper->method('findAllByUuids')->with([self::TABLE_UUID, self::OTHER_TABLE_UUID])
			->willReturn([$this->table(3, self::TABLE_UUID), $this->table(4, self::OTHER_TABLE_UUID)]);
		// two views have the uuid, the user can manage one of them
		$this->viewMapper->method('findAllByUuids')->with([self::VIEW_UUID])
			->willReturn([$this->view(8, 3, self::VIEW_UUID), $this->view(9, 4, self::VIEW_UUID)]);
		$this->permissionsService->method('canManageTable')->willReturnCallback(static fn (Table $table): bool => $table->getId() === 4);
		$this->permissionsService->method('canManageView')->willReturnCallback(static fn (View $view): bool => $view->getId() === 9);
		$tableService = $this->replaceService(TableService::class);
		$tableService->expects($this->once())->method('importTable')->willReturn($this->table(20, self::NEW_TABLE_UUID));
		$viewService = $this->replaceService(ViewService::class);
		$viewService->expects($this->once())->method('importView')->with(20)->willReturn($this->view(21, 20, self::NEW_VIEW_UUID));
		$this->replaceService(ColumnService::class)->method('findAllByTable')->willReturn([]);
		$nodeRelIdOf = null;
		$this->menuService->method('import')->willReturnCallback(static function (Context $context, array $menu, \Closure $nodeRelIds) use (&$nodeRelIdOf): array {
			$nodeRelIdOf = $nodeRelIds;
			return [];
		});
		$node = static fn (int $type, string $uuid): array => ['node_type' => $type, 'node_uuid' => $uuid, 'permissions' => 1];
		$viewScheme = ['uuid' => self::NEW_VIEW_UUID, 'title' => 'Open', 'emoji' => null, 'columnSettings' => [], 'sort' => [], 'filter' => []];

		$service = $this->getMockBuilder(ContextService::class)->setConstructorArgs($this->serviceArguments)->onlyMethods(['update'])->getMock();
		$service->expects($this->once())->method('update')
			->with(7, 'user-1', 'Intake', 'star', '', [
				['id' => 3, 'type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
				['id' => 4, 'type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
				['id' => 9, 'type' => Application::NODE_TYPE_VIEW, 'permissions' => 1],
				['id' => 20, 'type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
				['id' => 21, 'type' => Application::NODE_TYPE_VIEW, 'permissions' => 1],
			])
			->willReturn($after);

		$service->importScheme(7, 'Intake', 'star', '', [
			$node(Application::NODE_TYPE_TABLE, self::TABLE_UUID),
			$node(Application::NODE_TYPE_TABLE, self::OTHER_TABLE_UUID),
			$node(Application::NODE_TYPE_VIEW, self::VIEW_UUID),
			$node(Application::NODE_TYPE_TABLE, self::NEW_TABLE_UUID),
			$node(Application::NODE_TYPE_VIEW, self::NEW_VIEW_UUID),
		], ['addTables' => [$this->tableScheme(self::NEW_TABLE_UUID, [$viewScheme])], 'modifyTables' => []], 'user-1', []);

		$this->assertSame([101, 202, 203, null], [
			$nodeRelIdOf('table', self::TABLE_UUID),
			$nodeRelIdOf('table', self::NEW_TABLE_UUID),
			$nodeRelIdOf('view', self::NEW_VIEW_UUID),
			$nodeRelIdOf('view', self::OTHER_TABLE_UUID),
		]);
	}

	public function testUpdateAcceptsWhatTheImportKeepsAsANodeOfTheApplication(): void {
		// the update itself, not a mock: a current node the user cannot manage is kept
		$context = $this->contextWithNodes([101 => ['id' => 101, 'node_id' => 3, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1]]);
		$this->contextMapper->method('findById')->willReturn($context);
		$this->contextMapper->method('update')->willReturnArgument(0);
		$this->tableMapper->method('findAllByUuids')->willReturn([$this->table(3, self::TABLE_UUID)]);
		$this->permissionsService->method('canManageTable')->willReturn(false);
		$this->permissionsService->method('canManageNodeById')->willReturn(false);
		$this->contextNodeRelMapper->expects($this->never())->method('insert');
		$this->contextNodeRelMapper->expects($this->never())->method('delete');

		$imported = $this->service->importScheme(7, 'Intake', 'star', '', [
			['node_type' => Application::NODE_TYPE_TABLE, 'node_uuid' => self::TABLE_UUID, 'permissions' => 1],
		], ['addTables' => [], 'modifyTables' => []], 'user-1')['context'];

		$this->assertSame([101], array_keys($imported->getNodes()));
	}

	public function testImportingASchemeRefusesAViewUuidThatMatchesSeveralManageableViews(): void {
		$this->contextMapper->method('findById')->willReturn($this->contextWithNodes([]));
		$this->viewMapper->method('findAllByUuids')->willReturn([$this->view(8, 3, self::VIEW_UUID), $this->view(9, 4, self::VIEW_UUID)]);
		$this->permissionsService->method('canManageView')->willReturn(true);

		$this->expectException(BadRequestError::class);
		$this->expectExceptionMessage('Node 0 of the scheme matches more than one view here that the user can manage.');
		$this->service->importScheme(7, 'Intake', 'star', '', [['node_type' => Application::NODE_TYPE_VIEW, 'node_uuid' => self::VIEW_UUID, 'permissions' => 1]], ['addTables' => [], 'modifyTables' => []], 'user-1');
	}

	/**
	 * @param array<int, array<string, int>> $nodes
	 */
	private function contextWithNodes(array $nodes): Context {
		$context = new Context();
		$context->id = 7;
		$context->setNodes($nodes);
		$context->setPages([301 => ['id' => 301, 'page_type' => Page::TYPE_STARTPAGE, 'content' => []]]);
		return $context;
	}

	private function table(int $id, string $uuid): Table {
		$table = new Table();
		$table->id = $id;
		$table->setUuid($uuid);
		return $table;
	}

	private function view(int $id, int $tableId, string $uuid): View {
		$view = new View();
		$view->id = $id;
		$view->setTableId($tableId);
		$view->setUuid($uuid);
		return $view;
	}

	/**
	 * @param list<array<string, mixed>> $views
	 * @return array<string, mixed>
	 */
	private function tableScheme(mixed $uuid, array $views = []): array {
		return ['uuid' => $uuid, 'title' => 'T', 'emoji' => null, 'description' => '', 'archived' => false, 'columns' => [], 'views' => $views, 'columnOrder' => [], 'sort' => []];
	}
}
