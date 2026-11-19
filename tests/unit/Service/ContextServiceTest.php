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
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
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
	private MockObject $permissionsService;
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
	private MockObject $viewMapper;
	private MockObject $menuService;
	private MockObject $logger;

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
		$this->logger = $logger = $this->createMock(LoggerInterface::class);
		$this->permissionsService = $this->createMock(PermissionsService::class);
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

		$this->service = new ContextService(
			$this->contextMapper,
			$this->contextNodeRelMapper,
			$this->pageMapper,
			$this->pageContentMapper,
			$logger,
			$this->permissionsService,
			$this->userManager,
			$this->auditLogService,
			$this->dbConnection,
			$shareService,
			false,
			$navigationManager,
			$urlGenerator,
			$this->createMock(TableMapper::class),
			$this->viewMapper = $this->createMock(ViewMapper::class),
			$this->widgetService,
			new GridWidgetTypes($l10n),
			$this->menuItemMapper,
			$this->menuService,
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
		// the added relation in the shape the application holds its nodes
		$added = new class extends ContextNodeRelation {
			public function jsonSerialize(): array {
				return ['id' => 103, 'context_id' => 7, 'node_id' => 13, 'node_type' => 0, 'permissions' => 5];
			}
		};
		$added->id = 103;
		$added->setContextId(7);
		$this->contextNodeRelMapper->method('insert')->willReturn($added);
		$content = new class extends PageContent {
			public function jsonSerialize(): array {
				return ['id' => 503, 'page_id' => 301, 'node_rel_id' => 103, 'order' => 100];
			}
		};
		$content->id = 503;
		$content->setPageId(301);
		$this->pageContentMapper->method('insert')->willReturn($content);
		$this->pageContentMapper->method('findById')->with(503)->willReturn($content);
		$this->pageContentMapper->method('update')->willReturnArgument(0);
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

		$this->service->importContext([
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
		], 'user-1', [5 => 77], []);

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

		$this->service->importContext(['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => $pages], 'user-1', [4 => 40], []);

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

		$this->service->importContext(['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => [
			['id' => 1, 'page_type' => Page::TYPE_STARTPAGE, 'title' => 'Welcome', 'icon' => 'star', 'description' => 'Start here'],
			['id' => 2, 'page_type' => Page::TYPE_GRID, 'slug' => 'overview', 'title' => '  Overview ', 'icon' => 'no-such-icon', 'description' => str_repeat('a', 2001)],
		]], 'user-1', [4 => 40], []);

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

		$this->service->importContext(['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => $pages], 'user-1', [4 => 40], []);
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

		$this->service->importContext(['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => [
			['id' => 1, 'page_type' => Page::TYPE_STARTPAGE, 'slug' => 'home'],
		], 'menu' => $menu], 'user-1', [4 => 40], []);
	}

	public function testAFailedImportIsRolledBack(): void {
		$this->contextMapper->method('insert')->willReturnArgument(0);
		$this->contextNodeRelMapper->method('insert')->willReturnArgument(0);
		$this->pageMapper->method('insert')->willThrowException(new \RuntimeException('database error'));
		$this->dbConnection->expects($this->once())->method('beginTransaction');
		$this->dbConnection->expects($this->once())->method('rollBack');
		$this->dbConnection->expects($this->never())->method('commit');

		$this->expectException(\RuntimeException::class);
		$this->service->importContext(['name' => 'Projects', 'nodes' => [['id' => 1, 'node_id' => 4, 'node_type' => 0]], 'pages' => [
			['id' => 1, 'page_type' => Page::TYPE_STARTPAGE, 'slug' => 'home'],
		]], 'user-1', [4 => 40], []);
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

	public function testAnApplicationIsImportedOnceWithEachNodeOnItsOwnTableOrView(): void {
		$exported = [
			'name' => 'Projects',
			'nodes' => [
				['id' => 101, 'node_id' => 5, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
				['id' => 102, 'node_id' => 6, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 3],
				['id' => 103, 'node_id' => 5, 'node_type' => Application::NODE_TYPE_VIEW, 'permissions' => 1],
				['id' => 104, 'node_id' => 9, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
			],
			'pages' => [[
				'id' => 301,
				'page_type' => Page::TYPE_GRID,
				'slug' => 'overview',
				'content' => [['node_rel_id' => 101, 'order' => 10], ['node_rel_id' => 103, 'order' => 20], ['node_rel_id' => 104, 'order' => 30]],
				'widgets' => [
					self::dataWidget(0, ['nodeRelId' => 101]),
					self::dataWidget(5, ['nodeRelId' => 102]),
					self::dataWidget(10, ['nodeRelId' => 103]),
					self::dataWidget(15, ['nodeRelId' => 104]),
				],
			]],
		];
		$record = $this->recordImport();
		$contents = [];
		$this->pageContentMapper->method('insert')->willReturnCallback(static function (PageContent $content) use (&$contents): PageContent {
			$contents[] = $content->getNodeRelId();
			return $content;
		});

		$imported = $this->service->importContext($exported, 'user-1', [5 => 77, 6 => 78], [5 => 90]);

		self::assertSame('user-1', $imported->getOwnerId());
		self::assertSame(
			[[Application::NODE_TYPE_TABLE, 77, 1], [Application::NODE_TYPE_TABLE, 78, 3], [Application::NODE_TYPE_VIEW, 90, 1]],
			array_map(static fn (ContextNodeRelation $relation): array => [$relation->getNodeType(), $relation->getNodeId(), $relation->getPermissions()], $record['relations']),
		);
		// the grid page's own contents, then the start page the archive lacked, holding every node
		self::assertSame([501, 503, 501, 502, 503], $contents);
		self::assertSame([501, 502, 503, null], array_column($record['widgets'], 5));
	}

	public function testAnApplicationWithoutImportedNodesIsImportedWithItsPagesAndMenu(): void {
		$record = $this->recordImport();
		$this->logger->expects($this->exactly(3))->method('info')->with('Leaving a node out of an imported application: its table or view was not imported.');
		$this->menuService->expects($this->once())->method('import')->willReturn([]);

		$imported = $this->service->importContext([
			'name' => 'Projects',
			'nodes' => [
				['id' => 101, 'node_id' => 5, 'node_type' => Application::NODE_TYPE_TABLE],
				['id' => 102, 'node_id' => '6', 'node_type' => Application::NODE_TYPE_TABLE],
				['id' => 103, 'node_id' => 6, 'node_type' => 7],
			],
			'pages' => [['id' => 301, 'page_type' => Page::TYPE_GRID, 'slug' => 'notes', 'widgets' => [
				['type' => 'text', 'title' => 'Notes', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => ['text' => 'Hi'], 'source' => null],
			]]],
			'menu' => [['title' => 'Site', 'target' => ['url' => 'https://nextcloud.com']]],
		], 'user-1', [6 => 78], [5 => 90]);

		self::assertSame(['user-1', Application::OWNER_TYPE_USER], [$imported->getOwnerId(), $imported->getOwnerType()]);
		self::assertSame([], $record['relations']);
		self::assertSame([Page::TYPE_GRID, Page::TYPE_STARTPAGE], array_map(static fn (Page $page): string => $page->getPageType(), $record['pages']));
		self::assertSame(['Notes'], array_column($record['widgets'], 2));
	}

	public function testABrokenExportFileSkipsTheWidgetInsteadOfFailing(): void {
		$record = $this->recordImport();

		$this->service->importContext([
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
		], 'user-1', [5 => 77], []);

		self::assertSame(['overview', null], [$record['pages'][0]->getTitle(), $record['pages'][0]->getDescription()], 'an unusable title falls back to the slug');
		self::assertCount(1, $record['widgets']);
		self::assertSame([20, 502], [$record['widgets'][0][3]['gridY'], $record['widgets'][0][5]]);
	}

	public function testAnImportedPageKeepsAtMostTheMaximumNumberOfWidgets(): void {
		$record = $this->recordImport();
		$widgets = [];
		for ($i = 0; $i < GridWidgetTypes::MAX_WIDGETS_PER_PAGE + 5; $i++) {
			$widgets[] = ['type' => 'text', 'title' => null, 'gridX' => 0, 'gridY' => $i * 2, 'gridWidth' => 6, 'gridHeight' => 2, 'settings' => [], 'source' => null];
		}

		$this->service->importContext([
			'name' => 'Projects',
			'nodes' => [['id' => 101, 'node_id' => 5, 'node_type' => 0, 'permissions' => 1]],
			'pages' => [['id' => 301, 'page_type' => Page::TYPE_GRID, 'slug' => 'one', 'content' => [], 'widgets' => $widgets]],
		], 'user-1', [5 => 77], []);

		self::assertCount(GridWidgetTypes::MAX_WIDGETS_PER_PAGE, $record['widgets']);
	}

	public function testANodeWhosePermissionsAreNotANumberIsImportedWithoutPermissions(): void {
		$record = $this->recordImport();

		$this->service->importContext([
			'name' => 'Projects',
			'nodes' => [['id' => 101, 'node_id' => 5, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => '15']],
		], 'user-1', [5 => 77], []);

		self::assertSame([[77, 0]], array_map(static fn (ContextNodeRelation $relation): array => [$relation->getNodeId(), $relation->getPermissions()], $record['relations']));
	}

	public function testAnEmptyMenuIsNotImported(): void {
		$this->recordImport();
		$this->contextMapper->expects($this->never())->method('findById');
		$this->menuService->expects($this->never())->method('import');

		$this->service->importContext([
			'name' => 'Projects',
			'nodes' => [['id' => 101, 'node_id' => 5, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1]],
			'menu' => [],
		], 'user-1', [5 => 77], []);
	}

	public function testAMenuItemFindsItsViewByUuidAmongTheNodesOfTheApplication(): void {
		$record = $this->recordImport();
		$this->contextMapper->expects($this->never())->method('findById');
		$this->viewMapper->expects($this->never())->method('findByUuid');
		$nodeRelIdOf = null;
		$this->menuService->expects($this->once())->method('import')
			->willReturnCallback(static function (Context $context, array $menu, \Closure $resolve) use (&$nodeRelIdOf): array {
				$nodeRelIdOf = $resolve;
				return [];
			});

		$this->service->importContext([
			'name' => 'Projects',
			'nodes' => [
				['id' => 101, 'node_id' => 5, 'node_type' => Application::NODE_TYPE_TABLE, 'permissions' => 1],
				['id' => 102, 'node_id' => 6, 'node_type' => Application::NODE_TYPE_VIEW, 'permissions' => 1],
			],
			'menu' => [['title' => 'Team', 'target' => ['view' => 'shared-uuid']]],
		], 'user-1', [5 => 77], [6 => 90, 7 => 91], ['table' => ['table-uuid' => [77]], 'view' => ['shared-uuid' => [91, 90]]]);

		self::assertSame([501, 502], array_map(static fn (ContextNodeRelation $relation): int => $relation->getId(), $record['relations']));
		self::assertSame(
			[502, 501, null, null],
			[$nodeRelIdOf('view', 'shared-uuid'), $nodeRelIdOf('table', 'table-uuid'), $nodeRelIdOf('table', 'shared-uuid'), $nodeRelIdOf('view', 'unknown-uuid')],
		);
	}
}
