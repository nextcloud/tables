<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use InvalidArgumentException;
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
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\Helper\TextHelper;
use OCA\Tables\Model\ColumnSettings;
use OCA\Tables\Model\ContextScheme;
use OCA\Tables\Model\FilterSet;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\Model\ViewSettings;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCA\Tables\Validation\TechnicalNameValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
		private AuditLogServiceInterface $auditLogService,
		private IDBConnection $dbc,
		private ShareService $shareService,
		private bool $isCLI,
		protected INavigationManager $navigationManager,
		protected IURLGenerator $urlGenerator,
		private TableMapper $tableMapper,
		private ViewMapper $viewMapper,
		private WidgetService $widgetService,
		private GridWidgetTypes $gridWidgetTypes,
		private MenuItemMapper $menuItemMapper,
		private MenuService $menuService,
	) {
	}

	use TTransactional;

	/**
	 * @return Context[]
	 * @throws Exception
	 * @throws InternalError
	 */
	public function findAll(?string $userId, bool $withAllPages = true): array {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}
		return $this->contextMapper->findAll($userId, $withAllPages);
	}

	public function findForNavigation(string $userId): array {
		return $this->contextMapper->findForNavBar($userId);
	}

	public function addToNavigation(string $userId): void {
		$contexts = $this->findForNavigation($userId);
		foreach ($contexts as $context) {
			$iconRelPath = 'material/' . $context->getIcon() . '.svg';
			if (file_exists(__DIR__ . '/../../img/' . $iconRelPath)) {
				$iconUrl = $this->urlGenerator->imagePath(Application::APP_ID, $iconRelPath);
			} else {
				$iconUrl = $this->urlGenerator->imagePath('core', 'places/default-app-icon.svg');
			}

			$contextUrl = $this->urlGenerator->linkToRoute('tables.page.context', ['contextId' => $context->getId()]);

			$this->navigationManager->add([
				'id' => Application::APP_ID . '_application_' . $context->getId(),
				'name' => $context->getName(),
				'href' => $contextUrl,
				'icon' => $iconUrl,
				'order' => 500,
				'type' => 'link',
			]);
		}
	}

	/**
	 * @throws Exception
	 * @throws InternalError
	 * @throws NotFoundError
	 */
	public function findById(int $id, ?string $userId, bool $withAllPages = true): Context {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}

		return $this->contextMapper->findById($id, $userId, $withAllPages);
	}

	/**
	 * Creates an application with its start page and a menu of one item, without a
	 * title, opening the start page.
	 *
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

		$this->atomic(function () use ($context, $nodes): void {
			$this->contextMapper->insert($context);

			if (!empty($nodes)) {
				$context->resetUpdatedFields();
				$this->insertNodesFromArray($context, $nodes);
			}
			$this->insertPage($context);

			$startPageItem = new MenuItem();
			$startPageItem->setContextId($context->getId());
			$startPageItem->setOrder(0);
			$startPageItem->setPageId((int)array_key_first($context->getPages()));
			$this->menuItemMapper->insert($startPageItem);
		}, $this->dbc);
		$this->auditLogService->log(Operation::ContextCreated, 'Tables application with ID %s was created for user %s', [
			'contextId' => $context->getId(),
			'ownerId' => $ownerId,
			'nodes' => array_map(static fn (array $node): array => ['nodeType' => (int)$node['type'], 'nodeId' => (int)$node['id']], $nodes),
		]);

		return $context;
	}

	/**
	 * @psalm-param list<array{id: int, type: int, permissions?: int, order?: int}> $nodes
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws PermissionError|MultipleObjectsReturnedException
	 */
	public function update(int $contextId, string $userId, ?string $name, ?string $iconName, ?string $description, ?array $nodes): Context {
		// a refused or failing request changes nothing
		return $this->atomic(fn (): Context => $this->updateInTransaction($contextId, $userId, $name, $iconName, $description, $nodes), $this->dbc);
	}

	/**
	 * @throws Exception|PermissionError|NotFoundError
	 */
	private function updateInTransaction(int $contextId, string $userId, ?string $name, ?string $iconName, ?string $description, ?array $nodes): Context {
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
		$addedNodes = [];
		$removedNodes = [];
		$updatedNodes = [];
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
						$updatedNodes[] = [
							'nodeType' => $nodeRel->getNodeType(),
							'nodeId' => $nodeRel->getNodeId(),
							'previousPermissions' => $nodeRel->getPermissions(),
							'permissions' => (int)$node['permissions'],
						];
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

			foreach ($nodesBeingAdded as $node) {
				if (!$this->permissionsService->canManageNodeById((int)$node['type'], (int)$node['id'], $userId)) {
					throw new PermissionError(sprintf('Owner cannot manage node %d (type %d)', (int)$node['id'], (int)$node['type']));
				}
			}

			foreach ($nodesBeingRemoved as $node) {
				$removedNodes[] = ['nodeType' => (int)$node['node_type'], 'nodeId' => (int)$node['node_id']];
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
				$addedNodes[] = ['nodeType' => $nodeType, 'nodeId' => $nodeId];

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

			// Update order of nodes
			$startPageId = null;
			foreach ($currentPages as $page) {
				if ($page['page_type'] === Page::TYPE_STARTPAGE) {
					$startPageId = $page['id'];
					break;
				}
			}

			if ($startPageId !== null && isset($currentPages[$startPageId]['content'])) {
				$nodeTypeIdToRelId = [];
				foreach ($currentNodes as $relId => $nodeData) {
					$key = sprintf('t%di%d', $nodeData['node_type'], $nodeData['node_id']);
					$nodeTypeIdToRelId[$key] = $relId;
				}

				$relIdToContentId = [];
				foreach ($currentPages[$startPageId]['content'] as $contentId => $content) {
					$relIdToContentId[$content['node_rel_id']] = $contentId;
				}

				$contentsToUpdate = [];
				foreach ($nodes as $index => $node) {
					$key = sprintf('t%di%d', $node['type'], $node['id']);
					if (isset($nodeTypeIdToRelId[$key])) {
						$relId = $nodeTypeIdToRelId[$key];
						if (isset($relIdToContentId[$relId])) {
							$contentId = $relIdToContentId[$relId];
							$newOrder = ($index + 1) * 10;

							// Optimistic check: if order is already correct in memory, we might skip?
							// However, simpler to just add to update list, updateContentOrder should handle exceptions.
							// But to avoid DB writes if nothing changed:
							if ($currentPages[$startPageId]['content'][$contentId]['order'] !== $newOrder) {
								$contentsToUpdate[] = [
									'id' => $contentId,
									'order' => $newOrder
								];
								$currentPages[$startPageId]['content'][$contentId]['order'] = $newOrder;
								$hasUpdatedNodeInformation = true;
							}
						}
					}
				}

				if (!empty($contentsToUpdate)) {
					$this->updateContentOrder($startPageId, $contentsToUpdate);
				}
			}
		}

		$context = $this->contextMapper->update($context);
		if (isset($currentNodes, $currentPages) && $hasUpdatedNodeInformation) {
			$context->setNodes($currentNodes);
			$context->setPages($currentPages);
		}
		$this->auditLogService->log(Operation::ContextUpdated, 'Tables application with ID %s was updated', [
			'contextId' => $contextId,
			'addedNodes' => $addedNodes,
			'removedNodes' => $removedNodes,
			'updatedNodes' => $updatedNodes,
		]);

		return $context;
	}

	/**
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function delete(int $contextId, string $userId): Context {
		$context = $this->contextMapper->findById($contextId, $userId);

		$this->atomic(function () use ($context): void {
			// a page added in the meantime waits for this and then finds no application
			$this->pageMapper->lockContext($context->getId());
			$this->shareService->deleteAllForContext($context);
			$this->menuItemMapper->deleteByContextId($context->getId());
			$this->contextNodeRelMapper->deleteAllByContextId($context->getId());
			$pageIds = $this->pageMapper->getPageIdsForContext($context->getId());
			$this->widgetService->deleteByPageIds($pageIds);
			foreach ($pageIds as $pageId) {
				$this->pageContentMapper->deleteByPageId($pageId);
				$this->pageMapper->deleteByPageId($pageId);
			}
			$this->contextMapper->delete($context);
		}, $this->dbc);
		$this->auditLogService->log(Operation::ContextDeleted, 'Tables application with ID %s was deleted', [
			'contextId' => $context->getId(),
			'ownerId' => $context->getOwnerId(),
		]);
		return $context;
	}

	public function deleteNodeRel(int $nodeId, int $nodeType): void {
		try {
			$nodeRelIds = $this->contextNodeRelMapper->getRelIdsForNode($nodeId, $nodeType);
			$this->atomic(function () use ($nodeRelIds): void {
				$this->widgetService->deleteSourcesByNodeRelIds($nodeRelIds);
				$this->menuItemMapper->deleteByNodeRelIds($nodeRelIds);
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

		$oldOwnerId = $context->getOwnerId();
		$context->setOwnerId($newOwnerId);
		$context->setOwnerType($newOwnerType);

		try {
			$context = $this->atomic(function () use ($context, $contextId, $newOwnerId, $oldOwnerId) {
				$context = $this->contextMapper->update($context);
				$this->shareService->transferSharesForContext($contextId, $newOwnerId, $oldOwnerId);
				return $context;
			}, $this->dbc);
		} catch (\Exception $e) {
			$this->logger->error('Failed to transfer context ownership: ' . $e->getMessage(), ['exception' => $e]);
			throw new InternalError('Failed to transfer context ownership: ' . $e->getMessage());
		}

		$this->auditLogService->log(Operation::ContextTransferred, 'Tables application with ID %s was transferred to user %s', [
			'contextId' => $contextId,
			'toUserId' => $newOwnerId,
			'fromUserId' => $oldOwnerId,
		]);

		return $context;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function addNodeToContextById(int $contextId, int $nodeId, int $nodeType, int $permissions, ?string $userId): ContextNodeRelation {
		$context = $this->contextMapper->findById($contextId, $userId, false);
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
		return $this->atomic(function () use ($nodeRelationId): array {
			// the order of deleteNodeRel(): sources, contents, relation
			$this->widgetService->deleteSourcesByNodeRelIds([$nodeRelationId]);
			$contents = $this->pageContentMapper->findByNodeRelation($nodeRelationId);
			$this->pageContentMapper->deleteByNodeRelIds([$nodeRelationId]);
			$this->menuItemMapper->deleteByNodeRelIds([$nodeRelationId]);
			$nodeRelation = $this->removeNodeFromContextById($nodeRelationId);
			return [$nodeRelation, $contents];
		}, $this->dbc);
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
			$context = $this->contextMapper->findById($nodeRel->getContextId(), null, false);
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
		$page->setTechnicalName(Page::START_PAGE_NAME);
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

	/**
	 * The exported pages that can be imported: a start page or a grid page, and at most
	 * one start page. Others are skipped with a warning.
	 *
	 * @param array<array-key, mixed> $pages exported pages
	 * @return array<array-key, array<array-key, mixed>>
	 */
	private function importablePages(array $pages): array {
		$importable = [];
		$hasStartPage = false;
		foreach ($pages as $key => $pageArr) {
			$reason = self::pageSkipReason($pageArr, $hasStartPage);
			if ($reason !== null) {
				$this->logger->warning('Skipping an imported page: ' . $reason . '.');
				continue;
			}
			$importable[$key] = $pageArr;
		}
		return $importable;
	}

	/**
	 * Why an exported page cannot be imported, or null when it can: it must be a start
	 * page or a grid page, and an application has one start page.
	 *
	 * @param bool $hasStartPage whether an earlier page of the import is the start page; set when this one is
	 */
	private static function pageSkipReason(mixed $pageArr, bool &$hasStartPage): ?string {
		$type = is_array($pageArr) ? ($pageArr['page_type'] ?? null) : null;
		if ($type !== Page::TYPE_STARTPAGE && $type !== Page::TYPE_GRID) {
			return 'its type is not ' . Page::TYPE_STARTPAGE . ' or ' . Page::TYPE_GRID;
		}
		if ($type === Page::TYPE_STARTPAGE && $hasStartPage) {
			return 'an application has one start page';
		}
		$hasStartPage = $hasStartPage || $type === Page::TYPE_STARTPAGE;
		return null;
	}

	/**
	 * Imports an application of a user's data that holds the given table. Its menu keeps
	 * the links, the items for its pages and the items for the table, found by the uuid
	 * it was exported with.
	 *
	 * @param Table $table
	 * @param array $context
	 * @param int $tableOldId
	 * @param string|null $tableOldUuid
	 *
	 * @return Context|null
	 *
	 * @throws \Exception
	 */
	public function importContext(Table $table, array $context, int $tableOldId, ?string $tableOldUuid = null): ?Context {
		// checking if any node in $context['nodes'] matches the old table id
		$hasMatchingNode = false;
		if (!empty($context['nodes'])) {
			foreach ($context['nodes'] as $nodeArr) {
				if (($nodeArr['node_id'] ?? null) == $tableOldId) {
					$hasMatchingNode = true;
					break;
				}
			}
		}

		if (!$hasMatchingNode) {
			return null;
		}

		$newContext = new Context();
		$newContext->setName($context['name'] ?? '');
		$newContext->setIcon($context['iconName'] ?? '');
		$newContext->setDescription($context['description'] ?? '');
		$newContext->setOwnerId($table->getOwnership() ?? '');
		$newContext->setOwnerType($context['ownerType'] ?? 0);
		$this->contextMapper->insert($newContext);

		// insert nodes
		$nodeRelMap = [];
		// the relations of the table being imported, the only node a widget source can keep
		$sourceRelMap = [];
		if (!empty($context['nodes'])) {
			foreach ($context['nodes'] as $nodeArr) {
				$nodeType = $nodeArr['node_type'] ?? $nodeArr['nodeType'] ?? 0;
				$contextNodeRel = new ContextNodeRelation();
				$contextNodeRel->setContextId($newContext->getId());
				$contextNodeRel->setNodeId($table->getId());
				$contextNodeRel->setNodeType($nodeType);
				$contextNodeRel->setPermissions($nodeArr['permissions'] ?? 0);
				$contextNodeRel = $this->contextNodeRelMapper->insert($contextNodeRel);
				$oldRelId = self::importedKey($nodeArr['id'] ?? null);
				if ($oldRelId === null) {
					continue;
				}
				$nodeRelMap[$oldRelId] = $contextNodeRel;
				$oldNodeId = $nodeArr['node_id'] ?? $nodeArr['nodeId'] ?? null;
				if (is_numeric($nodeType) && (int)$nodeType === Application::NODE_TYPE_TABLE && is_numeric($oldNodeId) && (int)$oldNodeId === $tableOldId) {
					$sourceRelMap[$oldRelId] = $contextNodeRel;
				}
			}
		}

		// insert pages
		$pageMap = [];
		$pages = $this->importablePages(is_array($context['pages'] ?? null) ? $context['pages'] : []);
		$technicalNames = $this->importedPageNames($pages);
		$hasStartPage = false;
		foreach ($pages as $pageKey => $pageArr) {
			$page = new Page();
			$page->setContextId($newContext->getId());
			$page->setPageType($pageArr['page_type'] ?? '');
			$page->setTitle($this->importedText($pageArr['title'] ?? null, TextHelper::TITLE_MAX_LENGTH, 'page title'));
			$page->setIcon($this->importedIcon($pageArr['icon'] ?? null));
			$page->setDescription($this->importedText($pageArr['description'] ?? null, TextHelper::DESCRIPTION_MAX_LENGTH, 'page description'));
			if ($technicalNames[$pageKey] === null) {
				$page = $this->pageMapper->insertWithDefaultName($page);
			} else {
				$page->setTechnicalName($technicalNames[$pageKey]);
				$page = $this->pageMapper->insert($page);
			}
			$hasStartPage = $hasStartPage || $page->getPageType() === Page::TYPE_STARTPAGE;
			if ($page->getPageType() === Page::TYPE_GRID && trim((string)$page->getTitle()) === '') {
				// a grid page is named by its title, so it never stays without one
				$page->resetUpdatedFields();
				$page->setTitle((string)$page->getTechnicalName());
				$this->pageMapper->update($page);
			}
			$exportedPageId = self::importedKey($pageArr['id'] ?? null);
			if ($exportedPageId !== null) {
				$pageMap[$exportedPageId] = $page;
			}

			// insert page conten
			if (!empty($pageArr['content'])) {
				foreach ($pageArr['content'] as $contentArr) {
					$oldNodeRelId = self::importedKey($contentArr['node_rel_id'] ?? $contentArr['nodeRelId'] ?? null);
					$newNodeRel = $oldNodeRelId !== null ? ($nodeRelMap[$oldNodeRelId] ?? null) : null;
					if ($newNodeRel === null) {
						continue;
					}
					$pageContent = new PageContent();
					$pageContent->setPageId($page->getId());
					$pageContent->setNodeRelId($newNodeRel->getId());
					$pageContent->setOrder($contentArr['order'] ?? 100);
					$this->pageContentMapper->insert($pageContent);
				}
			}

			$widgets = is_array($pageArr['widgets'] ?? null) ? array_values($pageArr['widgets']) : [];
			if (count($widgets) > GridWidgetTypes::MAX_WIDGETS_PER_PAGE) {
				$this->logger->warning('Skipping ' . (count($widgets) - GridWidgetTypes::MAX_WIDGETS_PER_PAGE) . ' imported widgets: a page holds at most ' . GridWidgetTypes::MAX_WIDGETS_PER_PAGE . '.');
			}
			$this->importWidgets($page->getId(), array_slice($widgets, 0, GridWidgetTypes::MAX_WIDGETS_PER_PAGE), $sourceRelMap);
		}

		if (!$hasStartPage) {
			$this->logger->warning('An imported application had no start page; it gets a new one.');
			$this->insertImportedStartPage($newContext->getId(), in_array(Page::START_PAGE_NAME, $technicalNames, true), array_values($nodeRelMap));
		}

		if (is_array($context['menu'] ?? null)) {
			$tableRelation = reset($sourceRelMap);
			$this->logSkippedMenuItems($this->menuService->import(
				$this->contextMapper->findById($newContext->getId()),
				$context['menu'],
				static fn (string $kind, string $uuid): ?int => $kind === 'table' && $uuid === $tableOldUuid && $tableRelation !== false ? $tableRelation->getId() : null,
				static fn (int $pageId): ?int => ($pageMap[$pageId] ?? null)?->getId(),
			));
		}

		return $newContext;
	}

	/**
	 * A start page for an imported application that came without one, holding all of its
	 * tables and views. It is named `dashboard` unless an imported page took that name.
	 *
	 * @param list<ContextNodeRelation> $nodeRelations
	 * @throws Exception
	 */
	private function insertImportedStartPage(int $contextId, bool $startPageNameTaken, array $nodeRelations): void {
		$page = new Page();
		$page->setContextId($contextId);
		$page->setPageType(Page::TYPE_STARTPAGE);
		if ($startPageNameTaken) {
			$page = $this->pageMapper->insertWithDefaultName($page);
		} else {
			$page->setTechnicalName(Page::START_PAGE_NAME);
			$page = $this->pageMapper->insert($page);
		}
		foreach ($nodeRelations as $i => $nodeRelation) {
			$pageContent = new PageContent();
			$pageContent->setPageId($page->getId());
			$pageContent->setNodeRelId($nodeRelation->getId());
			$pageContent->setOrder(10 * ($i + 1));
			$this->pageContentMapper->insert($pageContent);
		}
	}

	/**
	 * @param list<array{index: int, title: ?string, reason: string}> $skipped
	 */
	private function logSkippedMenuItems(array $skipped): void {
		foreach ($skipped as $item) {
			$this->logger->warning('Skipping imported menu item ' . $item['index'] . ': ' . $item['reason']);
		}
	}

	/**
	 * The table or view (by kind, `table` or `view`) with a uuid, or null when it is not here.
	 *
	 * @throws Exception
	 */
	private function findNodeByUuid(string $kind, string $uuid): Table|View|null {
		try {
			return $kind === 'table' ? $this->tableMapper->findByUuid($uuid) : $this->viewMapper->findByUuid($uuid);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * The node relation of an application for an exported table or view, found by uuid.
	 */
	private function nodeRelIdByUuid(Context $context, string $kind, string $uuid): ?int {
		$node = $this->findNodeByUuid($kind, $uuid);
		if ($node === null) {
			return null;
		}
		$nodeType = $kind === 'table' ? Application::NODE_TYPE_TABLE : Application::NODE_TYPE_VIEW;
		foreach ($context->getNodes() ?? [] as $relId => $relation) {
			if ($relation['node_type'] === $nodeType && $relation['node_id'] === $node->getId()) {
				return (int)$relId;
			}
		}
		return null;
	}

	/**
	 * The technical names of the pages of an imported application, by page key. A valid
	 * exported name is kept unless an earlier page of the import uses it or it has the
	 * reserved form `page_<id>`, a start page without one gets `dashboard` while it is
	 * free, and null leaves the default name.
	 *
	 * @param array<array-key, mixed> $pages exported pages
	 * @return array<array-key, ?string>
	 */
	private function importedPageNames(array $pages): array {
		$names = [];
		$taken = [];
		foreach ($pages as $key => $pageArr) {
			$name = is_array($pageArr) ? ($pageArr['technical_name'] ?? null) : null;
			$names[$key] = null;
			if (is_string($name) && TechnicalNameValidator::isValid($name) && !Page::isDefaultName($name) && !isset($taken[$name])) {
				$names[$key] = $name;
				$taken[$name] = true;
			}
		}
		foreach ($pages as $key => $pageArr) {
			$isStartPage = is_array($pageArr) && ($pageArr['page_type'] ?? '') === Page::TYPE_STARTPAGE;
			if ($names[$key] === null && $isStartPage && !isset($taken[Page::START_PAGE_NAME])) {
				$names[$key] = Page::START_PAGE_NAME;
				$taken[Page::START_PAGE_NAME] = true;
			}
		}
		return $names;
	}

	/**
	 * The pages of an application, each with its widgets.
	 *
	 * @return array<int, array<string, mixed>>
	 * @throws Exception
	 */
	public function getPagesWithWidgets(Context $context): array {
		$pages = $context->getPages() ?? [];
		$widgets = $this->widgetService->findByPageIds(array_keys($pages));
		foreach ($pages as $pageId => $page) {
			$pages[$pageId]['widgets'] = $widgets[$pageId] ?? [];
		}
		return $pages;
	}

	/**
	 * Gives every page of the applications its widgets, with one query for all of them.
	 *
	 * @param Context[] $contexts
	 * @throws Exception
	 */
	public function addWidgetsToPages(array $contexts): void {
		$pageIds = [];
		foreach ($contexts as $context) {
			array_push($pageIds, ...array_keys($context->getPages() ?? []));
		}
		$widgets = $this->widgetService->findByPageIds($pageIds);
		foreach ($contexts as $context) {
			$pages = $context->getPages() ?? [];
			foreach ($pages as $pageId => $page) {
				$pages[$pageId]['widgets'] = $widgets[$pageId] ?? [];
			}
			$context->setPages($pages);
		}
	}

	/**
	 * Imports the widgets of a page the way the API would save them: a widget that does
	 * not pass is skipped with the reason, and a data widget keeps its source only when
	 * that table or view came along.
	 *
	 * @param list<mixed> $widgets
	 * @param array<array-key, ContextNodeRelation> $nodeRelMap new node relations by exported id
	 * @throws Exception
	 */
	private function importWidgets(int $pageId, array $widgets, array $nodeRelMap): void {
		$inputs = [];
		foreach ($widgets as $index => $widgetArr) {
			// an unknown icon, or a source on a type that shows none, is dropped, not the widget
			if (is_array($widgetArr) && isset($widgetArr['icon']) && (!is_string($widgetArr['icon']) || !IconHelper::isValid($widgetArr['icon']))) {
				$widgetArr['icon'] = null;
			}
			if (is_array($widgetArr) && is_string($widgetArr['type'] ?? null) && !($this->gridWidgetTypes->all()[$widgetArr['type']]['hasSource'] ?? true)) {
				$widgetArr['source'] = null;
			}
			try {
				$input = $this->gridWidgetTypes->readWidget($index, $widgetArr);
			} catch (BadRequestError $e) {
				$this->logger->warning('Skipping an imported widget: ' . $e->getMessage());
				continue;
			}
			$newNodeRel = $input->nodeRelId !== null ? ($nodeRelMap[$input->nodeRelId] ?? null) : null;
			$inputs[] = $input->withId(null)->withNodeRelId($newNodeRel?->getId());
		}
		$this->widgetService->insertAll($pageId, $inputs);
	}

	/**
	 * A widget of an exported scheme read the way the API reads one, with its source set
	 * aside: a scheme names a table or view by uuid, which the caller resolves. An unknown
	 * icon is dropped, not the widget.
	 *
	 * @return array{widget: WidgetInput, hasSource: bool, source: mixed}
	 * @throws BadRequestError
	 */
	private function readImportedWidget(int $index, mixed $widgetArr): array {
		if (!is_array($widgetArr)) {
			throw new BadRequestError('Widget ' . $index . ' must be an object.');
		}
		$source = $widgetArr['source'] ?? null;
		$widgetArr['source'] = null;
		if (isset($widgetArr['icon']) && (!is_string($widgetArr['icon']) || !IconHelper::isValid($widgetArr['icon']))) {
			$widgetArr['icon'] = null;
		}
		$widget = $this->gridWidgetTypes->readWidget($index, $widgetArr);
		return ['widget' => $widget->withId(null), 'hasSource' => $this->gridWidgetTypes->get($widget->type)['hasSource'], 'source' => $source];
	}

	/**
	 * An exported id usable as an array key, or null for a value of another type.
	 */
	private static function importedKey(mixed $id): int|string|null {
		return is_int($id) || is_string($id) ? $id : null;
	}

	/**
	 * An exported text, trimmed, or null when it is empty, not a text or too long.
	 */
	private function importedText(mixed $text, int $maxLength, string $what): ?string {
		try {
			return TextHelper::optional($text, $maxLength);
		} catch (\InvalidArgumentException $e) {
			$this->logger->warning('Dropping an imported ' . $what . ': it is ' . $e->getMessage() . '.');
			return null;
		}
	}

	/**
	 * An exported icon name, or null when this instance has no such icon.
	 */
	private function importedIcon(mixed $icon): ?string {
		return is_string($icon) && IconHelper::isValid($icon) ? $icon : null;
	}

	/**
	 * @param int $contextId
	 *
	 * @return ContextScheme
	 *
	 * @throws ContainerExceptionInterface
	 * @throws Exception
	 * @throws NotFoundError
	 * @throws NotFoundExceptionInterface
	 */
	public function getScheme(int $contextId, string $userId): ContextScheme {
		$context = $this->contextMapper->findById($contextId);
		$tableService = \OCP\Server::get(TableService::class);
		$viewService = \OCP\Server::get(ViewService::class);
		$nodes = $context->getNodes();
		$tables = [];

		foreach ($nodes as &$node) {
			if ($node['node_type'] === Application::NODE_TYPE_TABLE) {
				try {
					$table = $tableService->find($node['node_id']);
					$tableScheme = $tableService->getScheme($node['node_id'], $userId)->jsonSerialize();
					$tables[$node['node_id']] = $tableScheme;
					$node['node_uuid'] = $table->getUuid();
					$node['node_title'] = $table->getTitle();
				} catch (InternalError|PermissionError|NotFoundError $e) {
					$this->logger->error('Failed to enhance context scheme for table node: ' . $e->getMessage(), ['exception' => $e]);
				}
			}
			if ($node['node_type'] === Application::NODE_TYPE_VIEW) {
				try {
					$view = $viewService->find($node['node_id']);
					$node['node_uuid'] = $view->getUuid();
					$node['node_title'] = $view->getTitle();
					if (isset($tables[$view->getTableId()])) {
						continue;
					}
					$tables[$view->getTableId()] = $tableService->getScheme($view->getTableId(), $userId)->jsonSerialize();
				} catch (InternalError|PermissionError|NotFoundError $e) {
					$this->logger->error('Failed to enhance context scheme for view node: ' . $e->getMessage(), ['exception' => $e]);
				}
			}
		}

		return new ContextScheme(
			$context->getName(),
			$context->getIcon(),
			$context->getDescription(),
			array_values($nodes),
			self::exportPages($this->getPagesWithWidgets($context), $nodes),
			array_values($tables),
			$this->menuService->export($context),
		);
	}

	/**
	 * The pages of an application as a scheme carries them: the source of a widget also
	 * names its table or view by `targetType` and `targetUuid`, which an import resolves.
	 *
	 * @param array<int, array<string, mixed>> $pages the pages with their widgets
	 * @param array<array-key, array<string, mixed>> $nodes the nodes of the scheme, with their uuid
	 * @return array<int, array<string, mixed>>
	 */
	private static function exportPages(array $pages, array $nodes): array {
		$targets = [];
		foreach ($nodes as $node) {
			if (isset($node['node_uuid'])) {
				$targets[$node['id']] = [
					'targetType' => $node['node_type'] === Application::NODE_TYPE_TABLE ? 'table' : 'view',
					'targetUuid' => $node['node_uuid'],
				];
			}
		}
		foreach ($pages as $pageId => $page) {
			foreach ($page['widgets'] ?? [] as $index => $widget) {
				if (is_array($widget['source'] ?? null)) {
					$pages[$pageId]['widgets'][$index]['source'] += $targets[$widget['source']['nodeRelId']] ?? [];
				}
			}
		}
		return $pages;
	}

	/**
	 * @param int $contextId
	 * @param array $updateScheme
	 *
	 * @return array|array[]
	 *
	 * @throws Exception
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws BadRequestError
	 */
	public function compareSchemeChanges(int $contextId, array $updateScheme, string $userId): array {
		$context = $this->contextMapper->findById($contextId);
		$currentScheme = $this->getScheme($contextId, $userId);
		$tableService = \OCP\Server::get(TableService::class);

		$changes = [
			'name' => [
				'from' => $context->getName(),
				'to' => $updateScheme['name'] ?? $context->getName()
			],
			'icon' => [
				'from' => $context->getIcon(),
				'to' => $updateScheme['icon'] ?? $context->getIcon()
			],
			'description' => [
				'from' => $context->getDescription(),
				'to' => $updateScheme['description'] ?? $context->getDescription()
			],
			'nodes' => [
				'from' => $currentScheme->getNodes(),
				'to' => $updateScheme['nodes'] ?? $currentScheme->getNodes()
			],
		];

		$tables = $tableService->findAll();
		$tablesMap = [];
		foreach ($tables as $table) {
			$tablesMap[$table->getUuid()] = $table;
		}

		$updateSchemeTables = $updateScheme['tables'] ?? [];
		$changes['addTables'] = [];
		$changes['modifyTables'] = [];

		// Detect new tables
		foreach ($updateSchemeTables as $table) {
			if (!isset($tablesMap[$table['uuid']])) {
				$changes['addTables'][] = $table;
			}
		}

		// Detect modify tables
		foreach ($updateSchemeTables as $tableUpdateScheme) {
			if (isset($tablesMap[$tableUpdateScheme['uuid']])) {
				$tableLocalId = $tablesMap[$tableUpdateScheme['uuid']]->getId();
				$tableChanges = $tableService->compareTableSchemeChanges($tableLocalId, $tableUpdateScheme);
				if ($tableChanges['hasChanges']) {
					$changes['modifyTables'][$tableUpdateScheme['uuid']] = $tableChanges;
				}
			}
		}

		$schemePageIds = [];
		if (is_array($updateScheme['pages'] ?? null)) {
			$addedUuids = $this->addedSchemeUuids($updateSchemeTables, array_keys($tablesMap));
			$schemeNodes = $this->previewSchemeNodes($context, is_array($updateScheme['nodes'] ?? null) ? $updateScheme['nodes'] : [], $userId, $addedUuids);
			['pages' => $pages, 'skipped' => $skipped] = $this->resolveSchemePages($context, array_values($updateScheme['pages']), $schemeNodes);
			$changes['pages'] = [
				'from' => array_values(array_map(static fn (array $page): array => ['technicalName' => $page['technical_name'] ?? null, 'title' => $page['title'] ?? null], $context->getPages() ?? [])),
				'to' => array_column($pages, 'entry'),
				'skipped' => $skipped,
			];
			foreach ($pages as $page) {
				if ($page['exportedId'] !== null) {
					// a page the import adds is not here yet; any id passes the check
					$schemePageIds[$page['exportedId']] = $page['pageId'] ?? 0;
				}
			}
		}
		if (is_array($updateScheme['menu'] ?? null)) {
			$schemeUuids = [];
			foreach ($changes['nodes']['to'] ?? [] as $node) {
				$kind = ($node['node_type'] ?? null) === Application::NODE_TYPE_VIEW ? 'view' : 'table';
				$schemeUuids[$kind][(string)($node['node_uuid'] ?? '')] = true;
			}
			$pageIds = $this->menuService->schemePageIds($context, is_array($updateScheme['pages'] ?? null) ? $updateScheme['pages'] : []);
			['items' => $items, 'skipped' => $skipped] = $this->menuService->readExported(
				$context,
				$updateScheme['menu'],
				static fn (string $kind, string $uuid): ?int => isset($schemeUuids[$kind][$uuid]) ? 0 : null,
				static fn (int $pageId): ?int => $pageIds[$pageId] ?? $schemePageIds[$pageId] ?? null,
			);
			$changes['menu'] = [
				'from' => $currentScheme->getMenu(),
				'to' => $this->menuService->withLocalPages(array_column($items, 'entry'), $pageIds),
				'skipped' => $skipped,
			];
		}

		return $changes;
	}

	/**
	 * The nodes of a scheme that an import makes nodes of the application, by node type and
	 * uuid as {@see self::resolveSchemePages()} takes them: a node the application has, one
	 * the import adds, and one that is here and manageable by the user.
	 *
	 * @param array<array-key, mixed> $schemeNodes
	 * @param array<string, true> $addedUuids uuids of the tables and views the import adds
	 * @return array<string, ?int>
	 * @throws Exception
	 */
	private function previewSchemeNodes(Context $context, array $schemeNodes, string $userId, array $addedUuids): array {
		$current = [];
		foreach ($context->getNodes() ?? [] as $node) {
			$current[sprintf('t%di%d', $node['node_type'], $node['node_id'])] = true;
		}
		$nodes = [];
		foreach ($schemeNodes as $node) {
			$nodeType = is_array($node) ? ($node['node_type'] ?? null) : null;
			$uuid = is_array($node) ? ($node['node_uuid'] ?? null) : null;
			if (($nodeType !== Application::NODE_TYPE_TABLE && $nodeType !== Application::NODE_TYPE_VIEW) || !is_string($uuid)) {
				continue;
			}
			$found = $this->findNodeByUuid($nodeType === Application::NODE_TYPE_TABLE ? 'table' : 'view', $uuid);
			if (isset($addedUuids[$uuid])
				|| ($found !== null && (isset($current[sprintf('t%di%d', $nodeType, $found->getId())]) || $this->permissionsService->canManageNodeById($nodeType, $found->getId(), $userId)))) {
				$nodes[$nodeType . ':' . $uuid] = $found?->getId();
			}
		}
		return $nodes;
	}

	/**
	 * The uuids of the tables and views of a scheme that are not here, which an import adds.
	 *
	 * @param array<array-key, mixed> $schemeTables
	 * @param list<string> $localTableUuids
	 * @return array<string, true>
	 * @throws Exception
	 */
	private function addedSchemeUuids(array $schemeTables, array $localTableUuids): array {
		$localTableUuids = array_flip($localTableUuids);
		$addedUuids = [];
		foreach ($schemeTables as $table) {
			if (!is_array($table)) {
				continue;
			}
			if (is_string($table['uuid'] ?? null) && !isset($localTableUuids[$table['uuid']])) {
				$addedUuids[$table['uuid']] = true;
			}
			foreach (is_array($table['views'] ?? null) ? $table['views'] : [] as $view) {
				if (is_array($view) && is_string($view['uuid'] ?? null) && $this->findNodeByUuid('view', $view['uuid']) === null) {
					$addedUuids[$view['uuid']] = true;
				}
			}
		}
		return $addedUuids;
	}

	/**
	 * Checks the pages of a scheme against an application of this instance. Pages follow
	 * {@see self::pageSkipReason()}: the start page of the scheme is the start page of the
	 * application, and a grid page is the page with its technical name, or a new one. A
	 * widget is checked as the API would save it, and the table or view it shows, named by
	 * uuid, must be a node of the application once the nodes of the scheme are imported; a
	 * widget never adds a node. A widget that does not pass, that sits on the start page or
	 * that is past {@see GridWidgetTypes::MAX_WIDGETS_PER_PAGE} is skipped with the reason,
	 * and so is a page that cannot be imported.
	 *
	 * Each page comes with the id the scheme gives it, the id of the page it updates or
	 * the technical name a new page gets, and its entry with the kept widgets only.
	 *
	 * @param list<mixed> $schemePages
	 * @param array<string, ?int> $nodes the nodes of the application after the import, by node type and uuid (`<type>:<uuid>`), with their id when it is known
	 * @return array{pages: list<array{entry: array<array-key, mixed>, exportedId: ?int, pageId: ?int, technicalName: ?string, isGrid: bool, title: ?string, icon: ?string, description: ?string, widgets: list<array{widget: array{type: string, hasSource: bool, title: ?string, icon: ?string, description: ?string, position: array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}, settings: array<string, mixed>, source: mixed}, source: ?array{0: int, 1: ?int}}>}>, skipped: list<array{pageIndex: int, page: mixed, widgetIndex: ?int, widget: mixed, reason: string}>}
	 */
	private function resolveSchemePages(Context $context, array $schemePages, array $nodes): array {
		$startPageId = null;
		$pageIdsByName = [];
		foreach ($context->getPages() ?? [] as $pageId => $page) {
			if (($page['page_type'] ?? null) === Page::TYPE_STARTPAGE) {
				$startPageId ??= (int)$pageId;
			}
			if (is_string($page['technical_name'] ?? null)) {
				$pageIdsByName[$page['technical_name']] = (int)$pageId;
			}
		}

		$pages = [];
		$skipped = [];
		$usedNames = [];
		$hasStartPage = false;
		foreach ($schemePages as $pageIndex => $pageArr) {
			$skip = static function (string $reason, ?int $widgetIndex = null, mixed $widget = null) use (&$skipped, $pageIndex, $pageArr): void {
				$skipped[] = [
					'pageIndex' => $pageIndex,
					'page' => is_array($pageArr) ? ($pageArr['title'] ?? $pageArr['technical_name'] ?? null) : null,
					'widgetIndex' => $widgetIndex,
					'widget' => is_array($widget) ? ($widget['title'] ?? null) : null,
					'reason' => $reason,
				];
			};
			$reason = self::pageSkipReason($pageArr, $hasStartPage);
			if ($reason !== null || !is_array($pageArr)) {
				$skip($reason ?? 'it is not an object');
				continue;
			}
			$name = $pageArr['technical_name'] ?? null;
			$name = is_string($name) && TechnicalNameValidator::isValid($name) && !isset($usedNames[$name]) ? $name : null;
			$pageId = null;
			$technicalName = null;
			if (($pageArr['page_type'] ?? null) === Page::TYPE_STARTPAGE) {
				if ($startPageId === null) {
					$skip('the application has no start page');
					continue;
				}
				$pageId = $startPageId;
			} elseif ($name !== null && isset($pageIdsByName[$name])) {
				$pageId = $pageIdsByName[$name];
			} elseif ($name !== null && !Page::isDefaultName($name)) {
				$technicalName = $name;
			}
			if ($name !== null) {
				$usedNames[$name] = true;
			}
			$isGrid = $pageId === null || $pageId !== $startPageId;

			$widgets = [];
			$keptEntries = [];
			foreach (is_array($pageArr['widgets'] ?? null) ? array_values($pageArr['widgets']) : [] as $widgetIndex => $widgetArr) {
				if (!$isGrid) {
					$skip('only grid pages hold widgets', $widgetIndex, $widgetArr);
					continue;
				}
				if ($widgetIndex >= GridWidgetTypes::MAX_WIDGETS_PER_PAGE) {
					$skip('a page holds at most ' . GridWidgetTypes::MAX_WIDGETS_PER_PAGE . ' widgets', $widgetIndex, $widgetArr);
					continue;
				}
				try {
					$widget = $this->readImportedWidget($widgetIndex, $widgetArr);
				} catch (BadRequestError $e) {
					$skip($e->getMessage(), $widgetIndex, $widgetArr);
					continue;
				}
				$source = $widget['hasSource'] ? $widget['source'] : null;
				$node = null;
				if ($source !== null) {
					$targetType = is_array($source) ? ($source['targetType'] ?? null) : null;
					$targetUuid = is_array($source) ? ($source['targetUuid'] ?? null) : null;
					if (($targetType !== 'table' && $targetType !== 'view') || !is_string($targetUuid)) {
						$skip('its source names no table or view by uuid', $widgetIndex, $widgetArr);
						continue;
					}
					$nodeType = $targetType === 'table' ? Application::NODE_TYPE_TABLE : Application::NODE_TYPE_VIEW;
					if (!array_key_exists($nodeType . ':' . $targetUuid, $nodes)) {
						$skip('its ' . $targetType . ' is not one of the resources of the application', $widgetIndex, $widgetArr);
						continue;
					}
					$node = [$nodeType, $nodes[$nodeType . ':' . $targetUuid]];
				}
				$widgets[] = ['widget' => $widget['widget'], 'source' => $node];
				$keptEntries[] = $widgetArr;
			}

			$pages[] = [
				'entry' => ['widgets' => $keptEntries] + $pageArr,
				'exportedId' => is_int($pageArr['id'] ?? null) ? $pageArr['id'] : null,
				'pageId' => $pageId,
				'technicalName' => $technicalName,
				'isGrid' => $isGrid,
				'title' => $this->importedText($pageArr['title'] ?? null, TextHelper::TITLE_MAX_LENGTH, 'page title'),
				'icon' => $this->importedIcon($pageArr['icon'] ?? null),
				'description' => $this->importedText($pageArr['description'] ?? null, TextHelper::DESCRIPTION_MAX_LENGTH, 'page description'),
				'widgets' => $widgets,
			];
		}
		return ['pages' => $pages, 'skipped' => $skipped];
	}

	/**
	 * Saves the pages of a scheme as {@see self::resolveSchemePages()} resolved them. A page
	 * takes the title, icon and description of the scheme, and the widgets of a grid page
	 * replace its widgets. A new page without a free technical name gets the default name.
	 *
	 * @param list<array{exportedId: ?int, pageId: ?int, technicalName: ?string, isGrid: bool, title: ?string, icon: ?string, description: ?string, widgets: list<array{widget: array{type: string, hasSource: bool, title: ?string, icon: ?string, description: ?string, position: array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}, settings: array<string, mixed>, source: mixed}, source: ?array{0: int, 1: ?int}}>, ...<array-key, mixed>}> $pages
	 * @return array<string, int> ids of the pages by the technical name the scheme gives them
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 */
	private function importSchemePages(int $contextId, array $pages, string $userId): array {
		$relIds = [];
		foreach ($this->contextMapper->findById($contextId, $userId)->getNodes() ?? [] as $relId => $node) {
			$relIds[sprintf('t%di%d', $node['node_type'], $node['node_id'])] = (int)$relId;
		}
		$pageIds = [];
		foreach ($pages as $resolved) {
			$page = $resolved['pageId'] !== null ? $this->pageMapper->findById($resolved['pageId']) : new Page();
			$page->setTitle($resolved['title']);
			$page->setIcon($resolved['icon']);
			$page->setDescription($resolved['description']);
			if ($resolved['pageId'] !== null) {
				if ($page->getUpdatedFields() !== []) {
					$this->pageMapper->update($page);
				}
			} else {
				$page->setContextId($contextId);
				$page->setPageType(Page::TYPE_GRID);
				if ($resolved['technicalName'] === null) {
					$page = $this->pageMapper->insertWithDefaultName($page);
				} else {
					$page->setTechnicalName($resolved['technicalName']);
					$page = $this->pageMapper->insert($page);
				}
			}
			if ($resolved['exportedId'] !== null) {
				$pageIds[$resolved['exportedId']] = $page->getId();
			}
			if ($resolved['isGrid']) {
				$this->widgetService->syncPage($page->getId(), array_map(
					static fn (array $kept): WidgetInput => $kept['widget']->withNodeRelId($kept['source'] !== null ? ($relIds[sprintf('t%di%d', $kept['source'][0], $kept['source'][1])] ?? null) : null),
					$resolved['widgets'],
				));
			}
		}
		return $pageIds;
	}

	/**
	 * @param int $contextId
	 * @param string $name
	 * @param string $iconName
	 * @param string $description
	 * @param array $nodes
	 * @param array $tables
	 * @param string $userId
	 * @param list<mixed>|null $pages the pages of the scheme, see {@see self::resolveSchemePages()}; null leaves the pages alone
	 *
	 * @return Context
	 *
	 * @throws BadRequestError
	 * @throws ContainerExceptionInterface
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws NotFoundExceptionInterface
	 * @throws PermissionError
	 */
	public function importScheme(int $contextId, string $name, string $iconName, string $description, array $nodes, array $tables, string $userId, ?array $menu = null, ?array $pages = null): Context {
		// Validate the structure of the columns and views arrays
		if (!isset($tables['addTables']) || !is_array($tables['addTables'])
			|| !isset($tables['modifyTables']) || !is_array($tables['modifyTables'])) {
			throw new BadRequestError('Invalid tables structure provided.');
		}

		$tableService = \OCP\Server::get(TableService::class);
		$viewService = \OCP\Server::get(ViewService::class);
		$columnService = \OCP\Server::get(ColumnService::class);

		foreach ($tables['addTables'] as $tableScheme) {
			$tableService->validateTableScheme($tableScheme);
			$table = $tableService->importTable($tableScheme, $userId);
			foreach ($tableScheme['columns'] as $columnData) {
				$columnService->importColumn($table, $columnData);
			}
			$newColumns = $columnService->findAllByTable($table->getId(), $userId, $table);
			$columnsMap = [];
			foreach ($newColumns as $column) {
				$columnsMap[$column->getUuid()] = $column;
			}
			foreach ($tableScheme['views'] as $viewData) {
				$viewData['viewSettings'] = ViewSettings::createFromInputArray($viewData['viewSettings'] ?? [], $columnsMap)->jsonSerialize();
				$viewData['columnSettings'] = ColumnSettings::createViewSettingsFromInputArray($viewData['columnSettings'], $columnsMap)->jsonSerialize();
				$viewData['sort'] = SortRuleSet::createFromInputArray($viewData['sort'], $columnsMap)->jsonSerialize();
				$viewData['filter'] = FilterSet::createFromInputArray($viewData['filter'], $columnsMap)->jsonSerialize();
				$viewService->importView($table->getId(), $viewData, $userId);
			}
		}

		foreach ($tables['modifyTables'] as $tableScheme) {
			if (!isset($tableScheme['uuid'])) {
				throw new BadRequestError('Table scheme must include a UUID for modification.');
			}
			$tableService->validateTableScheme($tableScheme);
			$table = $this->tableMapper->findByUuid($tableScheme['uuid']);
			$tableService->updateTableStructure($table->getId(), $tableScheme['columns'], $tableScheme['views'], $tableScheme['columnOrder'], $tableScheme['sort'], $userId);
		}

		// Resolve node ids for the new tables and views
		$resolvedNodes = [];
		$nodeIds = [];
		foreach ($nodes as $node) {
			if ($node['node_type'] === Application::NODE_TYPE_TABLE) {
				$table = $this->tableMapper->findByUuid($node['node_uuid']);
				$resolvedNodes[] = [
					'id' => $table->getId(),
					'type' => $node['node_type'],
					'permissions' => $node['permissions'],
				];
				$nodeIds[$node['node_type'] . ':' . $node['node_uuid']] = $table->getId();
			} elseif ($node['node_type'] === Application::NODE_TYPE_VIEW) {
				$view = $this->viewMapper->findByUuid($node['node_uuid']);
				$resolvedNodes[] = [
					'id' => $view->getId(),
					'type' => $node['node_type'],
					'permissions' => $node['permissions'],
				];
				$nodeIds[$node['node_type'] . ':' . $node['node_uuid']] = $view->getId();
			}
		}

		$schemePages = null;
		if ($pages !== null) {
			['pages' => $schemePages, 'skipped' => $skipped] = $this->resolveSchemePages($this->contextMapper->findById($contextId, $userId), array_values($pages), $nodeIds);
			foreach ($skipped as $skip) {
				$this->logger->warning('Skipping ' . ($skip['widgetIndex'] === null ? '' : 'widget ' . $skip['widgetIndex'] . ' of ') . 'page ' . $skip['pageIndex'] . ' of an imported scheme: ' . $skip['reason']);
			}
		}
		$context = $this->update($contextId, $userId, $name, $iconName, $description, $resolvedNodes);
		$schemePageIds = $schemePages !== null ? $this->importSchemePages($contextId, $schemePages, $userId) : [];
		if ($menu !== null) {
			$withNodes = $this->contextMapper->findById($contextId, $userId);
			$this->logSkippedMenuItems($this->menuService->import(
				$withNodes,
				$menu,
				fn (string $kind, string $uuid): ?int => $this->nodeRelIdByUuid($withNodes, $kind, $uuid),
				static fn (int $pageId): ?int => $schemePageIds[$pageId] ?? null,
			));
		}

		return $schemePages !== null ? $this->contextMapper->findById($contextId, $userId) : $context;
	}
}
