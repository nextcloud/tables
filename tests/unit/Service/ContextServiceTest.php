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
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
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
	private MockObject $userManager;
	private MockObject $auditLogService;

	protected function setUp(): void {
		parent::setUp();

		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->contextNodeRelMapper = $this->createMock(ContextNodeRelationMapper::class);
		$this->pageMapper = $this->createMock(PageMapper::class);
		$this->pageContentMapper = $this->createMock(PageContentMapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$permissionsService = $this->createMock(PermissionsService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);
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
			$this->userManager,
			$this->auditLogService,
			$dbConnection,
			$shareService,
			false,
			$navigationManager,
			$urlGenerator,
			$this->createMock(TableMapper::class),
			$this->createMock(ViewMapper::class),
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

	private function buildPageContent(int $id, int $pageId, int $nodeRelId, int $order): PageContent {
		$pageContent = new PageContent();
		$pageContent->id = $id;
		$pageContent->setPageId($pageId);
		$pageContent->setNodeRelId($nodeRelId);
		$pageContent->setOrder($order);

		return $pageContent;
	}
}
