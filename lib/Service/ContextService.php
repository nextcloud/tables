<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Service;

use InvalidArgumentException;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\ContextNodeRelation;
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use Psr\Log\LoggerInterface;

class ContextService {

	public function __construct(
		private ContextMapper $contextMapper,
		private ContextNodeRelationMapper $contextNodeRelMapper,
		private PageMapper $pageMapper,
		private PageContentMapper $pageContentMapper,
		private LoggerInterface $logger,
		private PermissionsService $permissionsService,
		private IUserManager $userManager,
		private IEventDispatcher $eventDispatcher,
		private IDBConnection $dbc,
		private ShareService $shareService,
		private bool $isCLI,
		protected INavigationManager $navigationManager,
		protected IURLGenerator $urlGenerator,
	) {
	}

	use TTransactional;

	/**
	 * @return Context[]
	 * @throws Exception
	 * @throws InternalError
	 */
	public function findAll(?string $userId): array {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}
		return $this->contextMapper->findAll($userId);
	}

	public function findForNavigation(string $userId): array {
		return $this->contextMapper->findForNavBar($userId);
	}

	public function addToNavigation(string $userId): void {
		$contexts = $this->findForNavigation($userId);
		foreach ($contexts as $context) {
			$this->navigationManager->add(function () use ($context) {
				$iconRelPath = 'material/' . $context->getIcon() . '.svg';
				if (file_exists(__DIR__ . '/../../img/' . $iconRelPath)) {
					$iconUrl = $this->urlGenerator->imagePath(Application::APP_ID, $iconRelPath);
				} else {
					$iconUrl = $this->urlGenerator->imagePath('core', 'places/default-app-icon.svg');
				}

				$contextUrl = $this->urlGenerator->linkToRoute('tables.page.context', ['contextId' => $context->getId()]);

				return [
					'id' => Application::APP_ID . '_application_' . $context->getId(),
					'name' => $context->getName(),
					'href' => $contextUrl,
					'icon' => $iconUrl,
					'order' => 500,
					'type' => 'link',
				];
			});
		}
	}

	/**
	 * @throws Exception
	 * @throws InternalError
	 * @throws NotFoundError
	 */
	public function findById(int $id, ?string $userId): Context {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}

		return $this->contextMapper->findById($id, $userId);
	}

	/**
	 * @psalm-param list<array{id: int, type: int, permissions?: int, order?: int}> $nodes
	 * @throws Exception|PermissionError|InvalidArgumentException
	 */
	public function create(string $name, string $iconName, string $description, array $nodes, string $ownerId, int $ownerType): Context {
		$context = new Context();
		$context->setName(trim($name));
		$context->setIcon(trim($iconName));
		$context->setDescription(trim($description));
		$context->setOwnerId($ownerId);
		$context->setOwnerType($ownerType);


		$this->atomic(function () use ($context, $nodes) {
			$this->contextMapper->insert($context);

			if (!empty($nodes)) {
				$context->resetUpdatedFields();
				$this->insertNodesFromArray($context, $nodes);
			}
			$this->insertPage($context);
		}, $this->dbc);

		return $context;
	}

	/**
	 * @psalm-param list<array{id: int, type: int, permissions?: int, order?: int}> $nodes
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws PermissionError|MultipleObjectsReturnedException
	 */
	public function update(int $contextId, string $userId, ?string $name, ?string $iconName, ?string $description, ?array $nodes): Context {
		$context = $this->contextMapper->findById($contextId, $userId);

		if ($name !== null) {
			$context->setName(trim($name));
		}
		if ($iconName !== null) {
			$context->setIcon(trim($iconName));
		}
		if ($description !== null) {
			$context->setDescription(trim($description));
		}

		$hasUpdatedNodeInformation = false;
		if ($nodes !== null) {
			$currentNodes = $context->getNodes();
			$currentPages = $context->getPages();

			$nodesBeingRemoved = [];
			$nodesBeingAdded = [];
			$nodesBeingKept = [];

			// new node relationships do not have an ID. We can recognize them
			// through their nodeType and nodeIds. For this we need to transform
			// the known relationships` keys to a compatible format.
			$oldNodeResolvableIdMapper = [];
			foreach ($currentNodes as $i => $oldNode) {
				$key = sprintf('t%di%d', $oldNode['node_type'], $oldNode['node_id']);
				$oldNodeResolvableIdMapper[$key] = $i;
			}

			foreach ($nodes as $node) {
				$key = sprintf('t%di%d', $node['type'], $node['id']);
				if (isset($oldNodeResolvableIdMapper[$key])) {
					$nodesBeingKept[$key] = $node;
					if ($node['permissions'] !== $currentNodes[$oldNodeResolvableIdMapper[$key]]['permissions']) {
						$nodeRel = $this->contextNodeRelMapper->findById($currentNodes[$oldNodeResolvableIdMapper[$key]]['id']);
						$nodeRel->setPermissions($node['permissions']);
						$this->contextNodeRelMapper->update($nodeRel);
						$currentNodes[$oldNodeResolvableIdMapper[$key]]['permissions'] = $nodeRel->getPermissions();
						$hasUpdatedNodeInformation = true;
					}
					unset($oldNodeResolvableIdMapper[$key]);
					continue;
				}
				$nodesBeingAdded[$key] = $node;
			}

			foreach (array_diff_key($oldNodeResolvableIdMapper, $nodesBeingAdded, $nodesBeingKept) as $toRemoveId) {
				$nodesBeingRemoved[$toRemoveId] = $currentNodes[$toRemoveId];
			}
			unset($nodesBeingKept);

			$hasUpdatedNodeInformation = $hasUpdatedNodeInformation || !empty($nodesBeingAdded) || !empty($nodesBeingRemoved);

			foreach ($nodesBeingRemoved as $node) {
				/** @var ContextNodeRelation $removedNode */
				/** @var PageContent[] $removedContents */
				[$removedNode, $removedContents] = $this->removeNodeFromContextAndPages($node['id']);
				foreach ($removedContents as $removedContent) {
					unset($currentPages[$removedContent->getPageId()]['content'][$removedContent->getId()]);
				}
				unset($currentNodes[$removedNode->getId()]);
			}
			unset($nodesBeingRemoved);

			foreach ($nodesBeingAdded as $node) {
				$nodeType = (int)($node['type']);
				$nodeId = (int)($node['id']);
				if (!$this->permissionsService->canManageNodeById($nodeType, $nodeId, $userId)) {
					throw new PermissionError(sprintf('Owner cannot manage node %d (type %d)', $nodeId, $nodeType));
				}

				/** @var ContextNodeRelation $addedNode */
				/** @var PageContent $updatedContent */
				[$addedNode, $updatedContent] = $this->addNodeToContextAndStartpage(
					$contextId,
					$node['id'],
					$node['type'],
					$node['permissions'],
					$node['order'] ?? 100,
					$userId
				);
				$currentNodes[$addedNode->getId()] = $addedNode->jsonSerialize();
				$currentPages[$updatedContent->getPageId()]['content'][$updatedContent->getId()] = $updatedContent->jsonSerialize();
			}
			unset($nodesBeingAdded);
		}

		$context = $this->contextMapper->update($context);
		if (isset($currentNodes, $currentPages) && $hasUpdatedNodeInformation) {
			$context->setNodes($currentNodes);
			$context->setPages($currentPages);
		}

		return $context;
	}

	/**
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function delete(int $contextId, string $userId): Context {
		$context = $this->contextMapper->findById($contextId, $userId);

		$this->atomic(function () use ($context): void {
			$this->shareService->deleteAllForContext($context);
			$this->contextNodeRelMapper->deleteAllByContextId($context->getId());
			$pageIds = $this->pageMapper->getPageIdsForContext($context->getId());
			foreach ($pageIds as $pageId) {
				$this->pageContentMapper->deleteByPageId($pageId);
				$this->pageMapper->deleteByPageId($pageId);
			}
			$this->contextMapper->delete($context);
		}, $this->dbc);
		return $context;
	}

	public function deleteNodeRel(int $nodeId, int $nodeType): void {
		try {
			$nodeRelIds = $this->contextNodeRelMapper->getRelIdsForNode($nodeId, $nodeType);
			$this->atomic(function () use ($nodeRelIds) {
				$this->pageContentMapper->deleteByNodeRelIds($nodeRelIds);
				$this->contextNodeRelMapper->deleteByNodeRelIds($nodeRelIds);
			}, $this->dbc);
		} catch (Exception $e) {
			$this->logger->error('Something went wrong while deleting node relation for node id: ' . (string)$nodeId . ' and node type ' . (string)$nodeType, ['exception' => $e]);
		}
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws BadRequestError
	 */
	public function transfer(int $contextId, string $newOwnerId, int $newOwnerType): Context {
		$context = $this->contextMapper->findById($contextId);

		// the owner type check can be dropped as soon as NC 29 is the lowest supported version,
		// as the int range as defined in the Controller will be enforced by the Http/Dispatcher.
		if ($newOwnerType !== Application::OWNER_TYPE_USER) {
			throw new BadRequestError('Unsupported owner type');
		}

		if (!$this->userManager->userExists($newOwnerId)) {
			throw new BadRequestError('User does not exist');
		}

		$context->setOwnerId($newOwnerId);
		$context->setOwnerType($newOwnerType);

		$context = $this->contextMapper->update($context);

		$auditEvent = new CriticalActionPerformedEvent(
			sprintf('Tables application with ID %d was transferred to user %s',
				$contextId, $newOwnerId,
			)
		);

		$this->eventDispatcher->dispatchTyped($auditEvent);

		return $context;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function addNodeToContextById(int $contextId, int $nodeId, int $nodeType, int $permissions, ?string $userId): ContextNodeRelation {
		$context = $this->contextMapper->findById($contextId, $userId);
		return $this->addNodeToContext($context, $nodeId, $nodeType, $permissions);
	}

	/**
	 * @throws Exception
	 */
	public function removeNodeFromContext(ContextNodeRelation $nodeRelation): ContextNodeRelation {
		return $this->contextNodeRelMapper->delete($nodeRelation);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function removeNodeFromContextById(int $nodeRelationId): ContextNodeRelation {
		$nodeRelation = $this->contextNodeRelMapper->findById($nodeRelationId);
		return $this->contextNodeRelMapper->delete($nodeRelation);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function addNodeToContextAndStartpage(int $contextId, int $nodeId, int $nodeType, int $permissions, int $order, string $userId): array {
		$relation = $this->addNodeToContextById($contextId, $nodeId, $nodeType, $permissions, $userId);
		$pageContent = $this->addNodeRelToPage($relation, $order);
		return [$relation, $pageContent];
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function removeNodeFromContextAndPages(int $nodeRelationId): array {
		$nodeRelation = $this->removeNodeFromContextById($nodeRelationId);
		$contents = $this->removeNodeRelFromAllPages($nodeRelation);
		return [$nodeRelation, $contents];
	}

	/**
	 * @throws Exception
	 */
	public function addNodeToContext(Context $context, int $nodeId, int $nodeType, int $permissions): ContextNodeRelation {
		$contextNodeRel = new ContextNodeRelation();
		$contextNodeRel->setContextId($context->getId());
		$contextNodeRel->setNodeId($nodeId);
		$contextNodeRel->setNodeType($nodeType);
		$contextNodeRel->setPermissions($permissions);

		return $this->contextNodeRelMapper->insert($contextNodeRel);
	}

	public function addNodeRelToPage(ContextNodeRelation $nodeRel, ?int $order = null, ?int $pageId = null): PageContent {
		if ($pageId === null) {
			// when no page is given, find the startpage to add it to
			$context = $this->contextMapper->findById($nodeRel->getContextId());
			$pages = $context->getPages();
			foreach ($pages as $page) {
				if ($page['page_type'] === 'startpage') {
					$pageId = $page['id'];
					break;
				}
			}
		}

		$pageContent = $this->pageContentMapper->findByPageAndNodeRelation($pageId, $nodeRel->getId());

		if ($pageContent === null) {
			$pageContent = new PageContent();
			$pageContent->setPageId($pageId);
			$pageContent->setNodeRelId($nodeRel->getId());
			$pageContent->setOrder($order ?? 100); //FIXME: demand or calc order

			$pageContent = $this->pageContentMapper->insert($pageContent);
		}
		return $pageContent;
	}

	public function removeNodeRelFromAllPages(ContextNodeRelation $nodeRelation): array {
		$contents = $this->pageContentMapper->findByNodeRelation($nodeRelation->getId());
		/** @var PageContent $content */
		foreach ($contents as $content) {
			try {
				$this->pageContentMapper->delete($content);
			} catch (Exception $e) {
				$this->logger->warning('Failed to delete Contexts page content with ID {pcId}', [
					'pcId' => $content->getId(),
					'exception' => $e,
				]);
			}
		}
		return $contents;
	}

	public function updateContentOrder(int $pageId, array $contents): array {
		$updated = [];
		foreach ($contents as $content) {
			try {
				$updated[] = $this->updatePageContent($pageId, $content['id'], $content['order']);
			} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception|InvalidArgumentException $e) {
				$this->logger->info('Could not updated order of content with ID {cID}', [
					'cID' => $content['id'],
					'exception' => $e,
				]);
			}
		}
		return $updated;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InvalidArgumentException
	 */
	protected function updatePageContent(int $pageId, int $contentId, int $order): PageContent {
		$pageContent = $this->pageContentMapper->findById($contentId);
		if ($pageContent->getPageId() !== $pageId) {
			throw new InvalidArgumentException('Content does not belong to given page');
		}
		$pageContent->setOrder($order);
		return $this->pageContentMapper->update($pageContent);
	}

	protected function insertPage(Context $context): void {
		$page = new Page();
		$page->setContextId($context->getId());
		$page->setPageType(Page::TYPE_STARTPAGE);
		$this->pageMapper->insert($page);

		$addedPage = $page->jsonSerialize();

		$i = 1;
		$contextNodes = $context->getNodes();
		if ($contextNodes) {
			foreach ($contextNodes as $node) {
				$pageContent = new PageContent();
				$pageContent->setPageId($page->getId());
				$pageContent->setNodeRelId($node['id']);
				$pageContent->setOrder(10 * $i++);

				$this->pageContentMapper->insert($pageContent);

				$addedPage['content'][$pageContent->getId()] = $pageContent->jsonSerialize();
				// the content is already embedded in the page
				unset($addedPage['content'][$pageContent->getId()]['pageId']);
			}
		}

		$context->setPages([$addedPage['id'] => $addedPage]);
	}

	/**
	 * @psalm-param list<array{id: int, type: int, permissions?: int, order?: int}> $nodes
	 * @throws PermissionError|InvalidArgumentException
	 */
	protected function insertNodesFromArray(Context $context, array $nodes): void {
		$addedNodes = [];

		$userId = $context->getOwnerType() === Application::OWNER_TYPE_USER ? $context->getOwnerId() : null;
		foreach ($nodes as $node) {
			try {
				$nodeType = (int)($node['type']);
				$nodeId = (int)($node['id']);

				if (!$this->permissionsService->canManageNodeById($nodeType, $nodeId, $userId)) {
					throw new PermissionError(sprintf('Owner cannot manage node %d (type %d)', $nodeId, $nodeType));
				}
				$contextNodeRel = $this->addNodeToContext($context, $nodeId, $nodeType, $node['permissions'] ?? Application::PERMISSION_READ);
				$addedNodes[] = $contextNodeRel->jsonSerialize();
			} catch (Exception $e) {
				$this->logger->warning('Could not add node {ntype}/{nid} to context {cid}, skipping.', [
					'app' => Application::APP_ID,
					'ntype' => $node['type'],
					'nid' => $node['id'],
					'permissions' => $node['permissions'] ?? '',
					'cid' => $context['id'],
					'exception' => $e,
				]);
			}
		}
		$context->setNodes($addedNodes);
	}
}
