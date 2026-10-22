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
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContent;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\Model\ColumnSettings;
use OCA\Tables\Model\ContextScheme;
use OCA\Tables\Model\FilterSet;
use OCA\Tables\Model\MenuItemInput;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\Model\ViewSettings;
use OCA\Tables\Validation\TechnicalNameValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
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
		private IEventDispatcher $eventDispatcher,
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
		private IL10N $l10n,
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
		$contexts = $this->contextMapper->findAll($userId);
		foreach ($contexts as $context) {
			$this->markMenuForReader($context, $userId);
		}
		return $contexts;
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
	public function findById(int $id, ?string $userId): Context {
		if ($userId !== null && trim($userId) === '') {
			$userId = null;
		}
		if ($userId === null && !$this->isCLI) {
			$error = 'Try to set no user in context, but request is not allowed.';
			$this->logger->warning($error);
			throw new InternalError($error);
		}

		$context = $this->contextMapper->findById($id, $userId);
		$this->markMenuForReader($context, $userId);
		return $context;
	}

	/**
	 * Marks a menu item as broken for a reader who cannot open its table or view, the
	 * way an item whose target is gone is broken for everyone. A table or view of the
	 * application itself is open to everyone the application is shared with.
	 */
	private function markMenuForReader(Context $context, ?string $userId): void {
		$menuItems = $context->getMenuItems();
		if ($userId === null || !is_array($menuItems)) {
			return;
		}
		$readableNodes = [];
		foreach ($context->getNodes() ?? [] as $node) {
			if (((int)$node['permissions'] & Application::PERMISSION_READ) !== 0) {
				$readableNodes[sprintf('t%di%d', $node['node_type'], $node['node_id'])] = true;
			}
		}
		$changed = false;
		foreach ($menuItems as $index => $item) {
			$nodeType = match ($item['targetType'] ?? null) {
				MenuItem::TARGET_VIEW => Application::NODE_TYPE_VIEW,
				MenuItem::TARGET_TABLE => Application::NODE_TYPE_TABLE,
				default => null,
			};
			$nodeId = $item['targetId'] ?? null;
			if ($nodeType === null || !is_int($nodeId) || ($item['broken'] ?? false) || isset($readableNodes[sprintf('t%di%d', $nodeType, $nodeId)])) {
				continue;
			}
			if (!$this->permissionsService->canAccessNodeById($nodeType, $nodeId, $userId)) {
				$menuItems[$index]['broken'] = true;
				$changed = true;
			}
		}
		if ($changed) {
			$context->setMenuItems($menuItems);
		}
	}

	/**
	 * Creates an application with its start page. Without a menu it gets a "Dashboard"
	 * item opening the start page; a given menu, even an empty one, is stored as it is,
	 * where a page item without an id opens the start page.
	 *
	 * @psalm-param list<array{id: int, type: int, permissions?: int, order?: int}> $nodes
	 * @param list<MenuItemInput>|null $menuItems
	 * @throws Exception|PermissionError|InvalidArgumentException
	 */
	public function create(string $name, string $iconName, string $description, array $nodes, string $ownerId, int $ownerType, ?array $menuItems = null): Context {
		$context = new Context();
		$context->setName(trim($name));
		$context->setIcon(trim($iconName));
		$context->setDescription(trim($description));
		$context->setOwnerId($ownerId);
		$context->setOwnerType($ownerType);

		$this->atomic(function () use ($context, $nodes, $menuItems, $ownerId): void {
			$this->contextMapper->insert($context);

			if (!empty($nodes)) {
				$context->resetUpdatedFields();
				$this->insertNodesFromArray($context, $nodes);
			}
			$this->insertPage($context);
			$startPageId = self::startPageId($context->getPages() ?? []);
			$menu = $menuItems ?? [];
			if ($startPageId !== null) {
				$menu = $menuItems === null
					? [$this->dashboardItem($startPageId)]
					: array_map(static fn (MenuItemInput $item): MenuItemInput => $item->pointsAtNewStartPage() ? $item->withPageId($startPageId) : $item, $menuItems);
			}
			$this->replaceMenu($context, $menu, $ownerId);
		}, $this->dbc);

		$this->shapeAddedForResponse($context, [], [], true);
		return $context;
	}

	/**
	 * Puts the nodes and start page contents a save added into the shape the application
	 * endpoints have always returned for them: a node as its relation and a content entry
	 * as its row. While saving, every lookup uses the shape of the context mapper.
	 *
	 * @param array<array-key, true> $knownNodeIds relation ids the application had before the save
	 * @param array<array-key, true> $knownContentIds content ids the application had before the save
	 * @param bool $isNew a new application lists its nodes and leaves the page id out of its contents
	 */
	private function shapeAddedForResponse(Context $context, array $knownNodeIds, array $knownContentIds, bool $isNew): void {
		$nodes = $context->getNodes();
		if (is_array($nodes)) {
			foreach ($nodes as $relId => $node) {
				if (!isset($knownNodeIds[$relId]) && isset($node['node_id'])) {
					$nodes[$relId] = [
						'id' => $node['id'],
						'contextId' => $context->getId(),
						'nodeId' => $node['node_id'],
						'nodeType' => $node['node_type'],
						'permissions' => $node['permissions'],
					];
				}
			}
			$context->setNodes($isNew ? array_values($nodes) : $nodes);
		}
		$pages = $context->getPages();
		if (is_array($pages)) {
			foreach ($pages as $pageId => $page) {
				foreach ($page['content'] ?? [] as $contentId => $content) {
					if (isset($knownContentIds[$contentId]) || !isset($content['node_rel_id'])) {
						continue;
					}
					$pages[$pageId]['content'][$contentId] = $isNew
						? ['id' => $contentId, 'nodeRelId' => $content['node_rel_id'], 'order' => $content['order']]
						: ['id' => $contentId, 'pageId' => $pageId, 'nodeRelId' => $content['node_rel_id'], 'order' => $content['order']];
				}
			}
			$context->setPages($pages);
		}
	}

	/**
	 * The "Dashboard" item opening the start page, the menu of a new application created
	 * without one.
	 */
	private function dashboardItem(int $startPageId): MenuItemInput {
		return new MenuItemInput(
			label: $this->l10n->t('Dashboard'),
			icon: null,
			description: null,
			targetType: MenuItem::TARGET_PAGE,
			targetId: $startPageId,
			url: null,
			technicalName: Page::START_PAGE_NAME,
		);
	}

	/**
	 * @param array<array-key, mixed> $pages
	 */
	private static function startPageId(array $pages): ?int {
		foreach ($pages as $pageId => $page) {
			if (is_array($page) && ($page['page_type'] ?? $page['pageType'] ?? null) === Page::TYPE_STARTPAGE) {
				return (int)$pageId;
			}
		}
		return null;
	}

	/**
	 * Replaces the menu of an application and adds the views and tables it opens as nodes,
	 * so everyone the application is shared with can open them. Removing an item leaves
	 * its view or table in the application. A page item must open a page of this
	 * application; a stored one whose page is gone is kept and read as broken.
	 *
	 * @param list<MenuItemInput> $menuItems
	 * @throws Exception|PermissionError|InvalidArgumentException
	 */
	protected function replaceMenu(Context $context, array $menuItems, ?string $userId): void {
		if (count($menuItems) > MenuItem::MAX_ITEMS_PER_APPLICATION) {
			throw new InvalidArgumentException('An application menu holds at most ' . MenuItem::MAX_ITEMS_PER_APPLICATION . ' items.');
		}
		$storedMenu = array_values(array_filter($context->getMenuItems() ?? [], is_array(...)));
		$pageIds = array_map(intval(...), array_keys($context->getPages() ?? []));
		foreach ($storedMenu as $storedItem) {
			if (($storedItem['targetType'] ?? null) === MenuItem::TARGET_PAGE && is_int($storedItem['targetId'] ?? null)) {
				$pageIds[] = $storedItem['targetId'];
			}
		}
		foreach ($menuItems as $item) {
			if ($item->getTargetType() === MenuItem::TARGET_PAGE && !in_array($item->getTargetId(), $pageIds, true)) {
				throw new InvalidArgumentException('A menu item can only open a page of its own application.');
			}
		}

		$keptNames = $this->keptTechnicalNames($menuItems, $storedMenu);
		$this->addMenuTargetsAsNodes($context, $menuItems, $storedMenu, $userId);

		$this->menuItemMapper->deleteAllByContextId($context->getId());
		$this->insertMenuItems($context->getId(), $menuItems, $keptNames);

		$context->setMenuItems(array_map(
			static fn (MenuItem $menuItem): array => $menuItem->jsonSerialize(),
			$this->menuItemMapper->findByContextId($context->getId()),
		));
	}

	/**
	 * Inserts the items of a menu, top-level items first so a child can point at the id
	 * of its parent; the order keeps the sequence of the menu. An item with a kept
	 * technical name is saved under it and refused when another save took it; every
	 * other item is named `item_<id>`.
	 *
	 * @param list<MenuItemInput> $menuItems
	 * @param array<int, string> $keptNames technical names by position
	 * @throws Exception|InvalidArgumentException
	 */
	private function insertMenuItems(int $contextId, array $menuItems, array $keptNames): void {
		$topLevelIds = [];
		foreach ([false, true] as $childPass) {
			foreach ($menuItems as $position => $item) {
				$parentIndex = $item->getParentIndex();
				if (($parentIndex !== null) !== $childPass) {
					continue;
				}
				if ($parentIndex !== null && !isset($topLevelIds[$parentIndex])) {
					throw new InvalidArgumentException('A menu item can only sit under a top-level item of the same menu.');
				}
				$menuItem = new MenuItem();
				$menuItem->setContextId($contextId);
				$menuItem->setLabel($item->getLabel());
				$menuItem->setIcon($item->getIcon());
				$menuItem->setDescription($item->getDescription());
				$menuItem->setTargetType($item->getTargetType());
				$menuItem->setTargetId($item->getTargetId());
				$menuItem->setUrl($item->getUrl());
				$menuItem->setOrder(($position + 1) * 10);
				$menuItem->setParentId($parentIndex === null ? null : $topLevelIds[$parentIndex]);

				if (isset($keptNames[$position])) {
					$menuItem->setTechnicalName($keptNames[$position]);
					try {
						$this->menuItemMapper->insert($menuItem);
					} catch (Exception $e) {
						if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
							throw $e;
						}
						throw new InvalidArgumentException('The technical name ' . $keptNames[$position] . ' is already used by a menu item of this application.');
					}
				} else {
					$this->menuItemMapper->insertWithDefaultName($menuItem);
				}
				if ($parentIndex === null) {
					$topLevelIds[$position] = $menuItem->getId();
				}
			}
		}
	}

	/**
	 * The technical names items keep, by position: the one given, else the stored name
	 * of an item with the same target, so renaming or moving an item keeps it.
	 *
	 * @param list<MenuItemInput> $menuItems
	 * @param list<array<array-key, mixed>> $storedMenu
	 * @return array<int, string>
	 * @throws InvalidArgumentException when two items are given the same name or a given
	 *                                  name has the automatic form and is not the item's stored one
	 */
	private function keptTechnicalNames(array $menuItems, array $storedMenu): array {
		$storedNames = [];
		foreach ($storedMenu as $storedItem) {
			$targetType = $storedItem['targetType'] ?? null;
			$technicalName = $storedItem['technicalName'] ?? null;
			if (!is_string($targetType) || !is_string($technicalName) || $technicalName === '') {
				continue;
			}
			$targetId = is_int($storedItem['targetId'] ?? null) ? $storedItem['targetId'] : null;
			$url = is_string($storedItem['url'] ?? null) ? $storedItem['url'] : null;
			$storedNames[self::menuTargetIdentity($targetType, $targetId, $url)][] = $technicalName;
		}

		$names = [];
		$taken = [];
		foreach ($menuItems as $position => $item) {
			$technicalName = $item->getTechnicalName();
			if ($technicalName === null) {
				continue;
			}
			if (isset($taken[$technicalName])) {
				throw new InvalidArgumentException('Two menu items share the technical name "' . $technicalName . '".');
			}
			$identity = self::menuTargetIdentity($item->getTargetType(), $item->getTargetId(), $item->getUrl());
			if (MenuItem::isDefaultName($technicalName) && !in_array($technicalName, $storedNames[$identity] ?? [], true)) {
				throw new InvalidArgumentException('The technical name ' . $technicalName . ' is reserved for automatic names.');
			}
			$names[$position] = $technicalName;
			$taken[$technicalName] = true;
		}

		foreach ($menuItems as $position => $item) {
			if (isset($names[$position])) {
				continue;
			}
			foreach ($storedNames[self::menuTargetIdentity($item->getTargetType(), $item->getTargetId(), $item->getUrl())] ?? [] as $storedName) {
				if (!isset($taken[$storedName])) {
					$names[$position] = $storedName;
					$taken[$storedName] = true;
					break;
				}
			}
		}
		return $names;
	}

	private static function menuTargetIdentity(string $targetType, ?int $targetId, ?string $url): string {
		return $targetType . ':' . ($targetType === MenuItem::TARGET_URL ? (string)$url : (string)$targetId);
	}

	/**
	 * The node a menu target is stored as, keyed so nodes and targets can be compared.
	 *
	 * @return array{string, array{int, int}}|null
	 */
	private static function menuTargetNode(mixed $targetType, mixed $targetId): ?array {
		$nodeType = match ($targetType) {
			MenuItem::TARGET_VIEW => Application::NODE_TYPE_VIEW,
			MenuItem::TARGET_TABLE => Application::NODE_TYPE_TABLE,
			default => null,
		};
		if ($nodeType === null || !is_int($targetId)) {
			return null;
		}
		return [sprintf('t%di%d', $nodeType, $targetId), [$nodeType, $targetId]];
	}

	/**
	 * Adds the views and tables a menu opens to the application and its start page. A
	 * new target must exist and be manageable by the user, and every unusable one gets
	 * the same answer, so a save reveals nothing about the views and tables of others. A
	 * target that was in the stored menu before and is gone is kept.
	 *
	 * @param list<MenuItemInput> $menuItems
	 * @param list<array<array-key, mixed>> $storedMenu
	 * @throws Exception|PermissionError
	 */
	private function addMenuTargetsAsNodes(Context $context, array $menuItems, array $storedMenu, ?string $userId): void {
		$missing = [];
		foreach ($menuItems as $item) {
			$target = self::menuTargetNode($item->getTargetType(), $item->getTargetId());
			if ($target !== null) {
				$missing[$target[0]] = $target[1];
			}
		}
		$storedTargets = [];
		foreach ($storedMenu as $storedItem) {
			$target = self::menuTargetNode($storedItem['targetType'] ?? null, $storedItem['targetId'] ?? null);
			if ($target !== null) {
				$storedTargets[$target[0]] = true;
			}
		}
		$nodes = $context->getNodes() ?? [];
		foreach ($nodes as $node) {
			unset($missing[sprintf('t%di%d', $node['node_type'], $node['node_id'])]);
		}
		if ($missing === []) {
			return;
		}

		$targetIds = [Application::NODE_TYPE_VIEW => [], Application::NODE_TYPE_TABLE => []];
		foreach ($missing as [$nodeType, $nodeId]) {
			$targetIds[$nodeType][] = $nodeId;
		}
		$existing = [
			Application::NODE_TYPE_VIEW => $this->viewMapper->findMany($targetIds[Application::NODE_TYPE_VIEW]),
			Application::NODE_TYPE_TABLE => $this->tableMapper->findMany($targetIds[Application::NODE_TYPE_TABLE]),
		];

		$pages = $context->getPages() ?? [];
		$startPageId = self::startPageId($pages);
		foreach ($missing as $key => [$nodeType, $nodeId]) {
			$exists = isset($existing[$nodeType][$nodeId]);
			if (!$exists && isset($storedTargets[$key])) {
				continue;
			}
			if (!$exists || !$this->permissionsService->canManageNodeById($nodeType, $nodeId, $userId)) {
				throw new PermissionError(sprintf('Owner cannot manage node %d (type %d)', $nodeId, $nodeType));
			}
			$relation = $this->addNodeToContext($context, $nodeId, $nodeType, Application::PERMISSION_READ);
			$nodes[$relation->getId()] = $relation->toContextNode();
			if ($startPageId !== null) {
				$content = $this->addNodeRelToPage($relation, null, $startPageId);
				$pages[$startPageId]['content'][$content->getId()] = $content->toPageContent();
			}
		}
		$context->setNodes($nodes);
		$context->setPages($pages);
	}

	/**
	 * @psalm-param list<array{id: int, type: int, permissions?: int, order?: int}> $nodes
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws PermissionError|MultipleObjectsReturnedException
	 * @param list<MenuItemInput>|null $menuItems replaces the menu when given
	 * @throws InvalidArgumentException
	 */
	public function update(int $contextId, string $userId, ?string $name, ?string $iconName, ?string $description, ?array $nodes, ?array $menuItems = null): Context {
		// A transaction that reads before it writes can lose its write lock to a concurrent
		// request (SQLite answers "database is locked" at once); running it again succeeds.
		return $this->atomicRetry(
			fn (): Context => $this->applyUpdate($contextId, $userId, $name, $iconName, $description, $nodes, $menuItems),
			$this->dbc,
		);
	}

	/**
	 * @psalm-param list<array{id: int, type: int, permissions?: int, order?: int}> $nodes
	 * @throws Exception
	 * @throws DoesNotExistException
	 * @throws PermissionError|MultipleObjectsReturnedException
	 * @param list<MenuItemInput>|null $menuItems
	 */
	private function applyUpdate(int $contextId, string $userId, ?string $name, ?string $iconName, ?string $description, ?array $nodes, ?array $menuItems): Context {
		$context = $this->contextMapper->findById($contextId, $userId);
		$knownNodeIds = array_fill_keys(array_keys($context->getNodes() ?? []), true);
		$knownContentIds = [];
		foreach ($context->getPages() ?? [] as $page) {
			$knownContentIds += array_fill_keys(array_keys($page['content'] ?? []), true);
		}

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
				$currentNodes[$addedNode->getId()] = $addedNode->toContextNode();
				$currentPages[$updatedContent->getPageId()]['content'][$updatedContent->getId()] = $updatedContent->toPageContent();
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
		if ($menuItems !== null) {
			$this->replaceMenu($context, $menuItems, $userId);
		}

		$this->shapeAddedForResponse($context, $knownNodeIds, $knownContentIds, false);
		return $context;
	}

	/**
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function delete(int $contextId, string $userId): Context {
		$context = $this->contextMapper->findById($contextId, $userId);

		// Deleting the application first takes the write lock before anything is read, so on
		// SQLite a concurrent write fails this transaction at once and the retry runs it again.
		$this->atomicRetry(function () use ($context): void {
			$this->contextMapper->delete($context);
			$this->shareService->deleteAllForContext($context);
			$this->menuItemMapper->deleteAllByContextId($context->getId());
			$this->contextNodeRelMapper->deleteAllByContextId($context->getId());
			$pageIds = $this->pageMapper->getPageIdsForContext($context->getId());
			$this->widgetService->deleteByPageIds($pageIds);
			foreach ($pageIds as $pageId) {
				$this->pageContentMapper->deleteByPageId($pageId);
				$this->pageMapper->deleteByPageId($pageId);
			}
		}, $this->dbc);
		return $context;
	}

	public function deleteNodeRel(int $nodeId, int $nodeType): void {
		try {
			$nodeRelIds = $this->contextNodeRelMapper->getRelIdsForNode($nodeId, $nodeType);
			$this->atomic(function () use ($nodeRelIds): void {
				$this->widgetService->deleteSourcesByNodeRelIds($nodeRelIds);
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

		$auditEvent = new CriticalActionPerformedEvent(
			sprintf(
				'Tables application with ID %d was transferred to user %s',
				$contextId,
				$newOwnerId,
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
		return $this->atomic(function () use ($nodeRelationId): array {
			$this->widgetService->deleteSourcesByNodeRelIds([$nodeRelationId]);
			$nodeRelation = $this->removeNodeFromContextById($nodeRelationId);
			$contents = $this->removeNodeRelFromAllPages($nodeRelation);
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

				$addedPage['content'][$pageContent->getId()] = $pageContent->toPageContent();
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
				$addedNodes[$contextNodeRel->getId()] = $contextNodeRel->toContextNode();
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
	 * @param Table $table
	 * @param array $context
	 * @param int $tableOldId
	 *
	 * @return Context|null
	 *
	 * @throws \Exception
	 */
	public function importContext(Table $table, array $context, int $tableOldId): ?Context {
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
		if (!empty($context['pages'])) {
			$technicalNames = $this->importedPageNames($context['pages']);
			foreach ($context['pages'] as $pageKey => $pageArr) {
				$page = new Page();
				$page->setContextId($newContext->getId());
				$page->setPageType($pageArr['page_type'] ?? $pageArr['pageType'] ?? '');
				$page->setTitle($this->importedText($pageArr['title'] ?? null, Page::TITLE_MAX_LENGTH, 'page title'));
				$page->setIcon($this->importedIcon($pageArr['icon'] ?? null));
				$page->setDescription($this->importedText($pageArr['description'] ?? null, Page::DESCRIPTION_MAX_LENGTH, 'page description'));
				if ($technicalNames[$pageKey] === null) {
					$page = $this->pageMapper->insertWithDefaultName($page);
				} else {
					$page->setTechnicalName($technicalNames[$pageKey]);
					$page = $this->pageMapper->insert($page);
				}
				$oldPageId = self::importedKey($pageArr['id'] ?? null);
				if ($oldPageId !== null) {
					$pageMap[$oldPageId] = $page;
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
				foreach (array_slice($widgets, 0, GridWidgetTypes::MAX_WIDGETS_PER_PAGE) as $index => $widgetArr) {
					$this->importWidget($page->getId(), $index, $widgetArr, $sourceRelMap);
				}
			}
		}

		if (is_array($context['menuItems'] ?? null)) {
			$this->importContextMenu($newContext->getId(), $context['menuItems'], $pageMap, $tableOldId, $table->getId());
		}

		return $newContext;
	}

	/**
	 * Inserts the exported menu of an imported application. An item opening the imported
	 * table or one of the imported pages points at its new id and a link is kept; an item
	 * opening anything else, or that is not valid, is skipped with a warning. A parent is
	 * found by its exported id and the menu is nested by {@see self::nestImportedMenu()}.
	 * At most {@see MenuItem::MAX_ITEMS_PER_APPLICATION} items are kept.
	 *
	 * @param array<array-key, mixed> $menuItems exported menu items
	 * @param array<array-key, Page> $pageMap imported pages by their exported id
	 * @throws Exception|InvalidArgumentException
	 */
	private function importContextMenu(int $contextId, array $menuItems, array $pageMap, int $tableOldId, int $tableId): void {
		$kept = [];
		foreach (array_values($menuItems) as $index => $item) {
			if (!is_array($item)) {
				$this->skipImportedMenuItem($index, 'it is not an object');
				continue;
			}
			$id = $item['id'] ?? null;
			$parentId = $item['parentId'] ?? null;
			$exportedTargetId = $item['targetId'] ?? null;
			if (!self::isIdOrNull($id) || !self::isIdOrNull($parentId) || !self::isIdOrNull($exportedTargetId)) {
				$this->skipImportedMenuItem($index, 'its id, parentId or targetId is not a number');
				continue;
			}
			$targetType = $item['targetType'] ?? null;
			$targetId = match ($targetType) {
				MenuItem::TARGET_TABLE => $exportedTargetId === $tableOldId ? $tableId : null,
				MenuItem::TARGET_PAGE => $exportedTargetId !== null && isset($pageMap[$exportedTargetId]) ? $pageMap[$exportedTargetId]->getId() : null,
				default => null,
			};
			if (in_array($targetType, [MenuItem::TARGET_TABLE, MenuItem::TARGET_PAGE, MenuItem::TARGET_VIEW], true) && $targetId === null) {
				$this->skipImportedMenuItem($index, 'its ' . $targetType . ' is not part of this import');
				continue;
			}
			if (count($kept) >= MenuItem::MAX_ITEMS_PER_APPLICATION) {
				$this->skipImportedMenuItem($index, 'a menu holds at most ' . MenuItem::MAX_ITEMS_PER_APPLICATION . ' items');
				continue;
			}
			try {
				$input = self::importedMenuInput($item, $targetType, $targetId);
			} catch (InvalidArgumentException $e) {
				$this->skipImportedMenuItem($index, $e->getMessage());
				continue;
			}
			$kept[] = ['input' => $input, 'ref' => $id === null ? null : (string)$id, 'parent' => $parentId === null ? null : (string)$parentId];
		}

		$menu = $this->nestImportedMenu($kept, 'imported menu item');
		$this->insertMenuItems($contextId, $menu, array_filter(array_map(static fn (MenuItemInput $item): ?string => $item->getTechnicalName(), $menu)));
	}

	private static function isIdOrNull(mixed $id): bool {
		return $id === null || is_int($id);
	}

	private function skipImportedMenuItem(int|string $index, string $reason): void {
		$this->logger->warning('Skipping imported menu item ' . $index . ': ' . $reason);
	}

	/**
	 * The menu item of an imported entry. A technical name that is not valid or has the
	 * automatic form `item_<id>` is dropped, so the item gets a name of its own.
	 *
	 * @param array<array-key, mixed> $entry
	 * @throws InvalidArgumentException when the item is not valid
	 */
	private static function importedMenuInput(array $entry, mixed $targetType, ?int $targetId): MenuItemInput {
		$name = $entry['technicalName'] ?? null;
		$keepName = is_string($name) && TechnicalNameValidator::isValid($name) && !MenuItem::isDefaultName($name);
		return MenuItemInput::createFromInputArray([
			'label' => $entry['label'] ?? null,
			'icon' => $entry['icon'] ?? null,
			'description' => $entry['description'] ?? null,
			'targetType' => $targetType,
			'targetId' => $targetId,
			'url' => $entry['url'] ?? null,
			'technicalName' => $keepName ? $name : null,
		]);
	}

	/**
	 * Nests the items of an imported menu one level deep. An item sits under its parent
	 * when that is a top-level item of the import; a child whose parent was skipped or is
	 * a child itself moves to the top. A technical name taken by an earlier item is
	 * dropped.
	 *
	 * @param list<array{input: MenuItemInput, ref: ?string, parent: ?string, entry?: array<array-key, mixed>}> $items in menu order, with the keys the item and its parent are exported under
	 * @return list<MenuItemInput>
	 */
	private function nestImportedMenu(array $items, string $what): array {
		$keptRefs = [];
		foreach ($items as $item) {
			if ($item['ref'] !== null) {
				$keptRefs[$item['ref']] = true;
			}
		}
		$topLevelPositions = [];
		foreach ($items as $position => $item) {
			$isTopLevel = $item['parent'] === null || !isset($keptRefs[$item['parent']]);
			if ($isTopLevel && $item['ref'] !== null && !isset($topLevelPositions[$item['ref']])) {
				$topLevelPositions[$item['ref']] = $position;
			}
		}

		$menu = [];
		$usedNames = [];
		foreach ($items as $position => $item) {
			$input = $item['input'];
			$parentIndex = $item['parent'] !== null ? ($topLevelPositions[$item['parent']] ?? null) : null;
			if ($item['parent'] !== null && $parentIndex === null) {
				$this->logger->warning('Moving ' . $what . ' ' . $position . ' to the top of the menu: its parent was skipped or is a child itself.');
			}
			$technicalName = $input->getTechnicalName();
			if ($technicalName !== null && isset($usedNames[$technicalName])) {
				$technicalName = null;
			}
			if ($technicalName !== null) {
				$usedNames[$technicalName] = true;
			}
			$menu[] = new MenuItemInput(
				$input->getLabel(), $input->getIcon(), $input->getDescription(), $input->getTargetType(),
				$input->getTargetId(), $input->getUrl(), $technicalName, $parentIndex,
			);
		}
		return $menu;
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
			$name = is_array($pageArr) ? ($pageArr['technical_name'] ?? $pageArr['technicalName'] ?? null) : null;
			$names[$key] = null;
			if (is_string($name) && TechnicalNameValidator::isValid($name) && !Page::isDefaultName($name) && !isset($taken[$name])) {
				$names[$key] = $name;
				$taken[$name] = true;
			}
		}
		foreach ($pages as $key => $pageArr) {
			$isStartPage = is_array($pageArr) && ($pageArr['page_type'] ?? $pageArr['pageType'] ?? '') === Page::TYPE_STARTPAGE;
			if ($names[$key] === null && $isStartPage && !isset($taken[Page::START_PAGE_NAME])) {
				$names[$key] = Page::START_PAGE_NAME;
				$taken[Page::START_PAGE_NAME] = true;
			}
		}
		return $names;
	}

	/**
	 * The pages of an application with the widgets of each page under `widgets`.
	 *
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
	 * Inserts an exported widget on a page. Its source is remapped to the imported node
	 * relation of the table being imported, and dropped when it showed another node. A
	 * widget that would not be saved through the API, for its type, settings, title,
	 * description, place on the grid or the shape of its source, is skipped.
	 *
	 * @param array<array-key, ContextNodeRelation> $nodeRelMap imported node relations of the table by their exported id
	 * @throws Exception
	 * @throws \JsonException
	 */
	private function importWidget(int $pageId, int $index, mixed $widgetArr, array $nodeRelMap): void {
		if (!is_array($widgetArr) || !is_string($widgetArr['type'] ?? null)) {
			return;
		}
		try {
			$hasSource = $this->gridWidgetTypes->get($widgetArr['type'])['hasSource'];
			$settings = $this->gridWidgetTypes->sanitizeSettings($widgetArr['type'], $widgetArr['settings'] ?? null);
			$title = $this->gridWidgetTypes->validateTitle($index, $widgetArr['title'] ?? null);
			$description = $this->gridWidgetTypes->validateDescription($index, $widgetArr['description'] ?? null);
			$position = $this->gridWidgetTypes->validatePosition($index, $widgetArr);
			$source = $widgetArr['source'] ?? null;
			$oldNodeRelId = is_array($source) ? ($source['nodeRelId'] ?? null) : null;
			if (($source !== null && !is_array($source)) || ($oldNodeRelId !== null && !is_int($oldNodeRelId))) {
				throw new BadRequestError('The source of widget ' . $index . ' must be an object with a numeric nodeRelId.');
			}
		} catch (BadRequestError $e) {
			$this->logger->warning('Skipping an imported widget: ' . $e->getMessage(), ['exception' => $e]);
			return;
		}
		$newNodeRel = $hasSource && $oldNodeRelId !== null ? ($nodeRelMap[$oldNodeRelId] ?? null) : null;
		$this->widgetService->insert(
			$pageId,
			$widgetArr['type'],
			$title,
			$position,
			$settings,
			$newNodeRel?->getId(),
			$this->importedIcon($widgetArr['icon'] ?? null),
			$description,
		);
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
		if ($text === null) {
			return null;
		}
		if (!is_string($text) || mb_strlen(trim($text)) > $maxLength) {
			$this->logger->warning('Dropping an imported ' . $what . ': it is not a text of at most ' . $maxLength . ' characters.');
			return null;
		}
		$text = trim($text);
		return $text === '' ? null : $text;
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
			$this->getPagesWithWidgets($context),
			array_values($tables),
			$this->exportMenu($context, $nodes),
		);
	}

	/**
	 * The menu of an application as a scheme carries it: a table or view by its uuid, a
	 * page and a parent by their technical name. A broken item is left out.
	 *
	 * @param array<array-key, array<string, mixed>> $nodes the nodes of the scheme, with their uuid
	 * @return list<array{label: string, icon: ?string, description: ?string, targetType: string, technicalName: string, url: ?string, targetUuid: ?string, targetPage: ?string, parent: ?string}>
	 */
	private function exportMenu(Context $context, array $nodes): array {
		$uuids = [];
		foreach ($nodes as $node) {
			if (isset($node['node_uuid'])) {
				$uuids[sprintf('t%di%d', $node['node_type'], $node['node_id'])] = $node['node_uuid'];
			}
		}
		$pageNames = [];
		foreach ($context->getPages() ?? [] as $pageId => $page) {
			$pageNames[(int)$pageId] = $page['technical_name'] ?? null;
		}
		$names = [];
		foreach ($context->getMenuItems() ?? [] as $item) {
			$names[$item['id']] = $item['technicalName'];
		}

		$menu = [];
		foreach ($context->getMenuItems() ?? [] as $item) {
			if ($item['broken']) {
				continue;
			}
			$target = self::menuTargetNode($item['targetType'], $item['targetId']);
			$targetUuid = $target !== null ? ($uuids[$target[0]] ?? null) : null;
			$targetPage = $item['targetType'] === MenuItem::TARGET_PAGE ? ($pageNames[$item['targetId']] ?? null) : null;
			if (($target !== null && $targetUuid === null) || ($item['targetType'] === MenuItem::TARGET_PAGE && $targetPage === null)) {
				continue;
			}
			$menu[] = [
				'label' => $item['label'],
				'icon' => $item['icon'],
				'description' => $item['description'],
				'targetType' => $item['targetType'],
				'technicalName' => $item['technicalName'],
				'url' => $item['url'],
				'targetUuid' => $targetUuid,
				'targetPage' => $targetPage,
				'parent' => $item['parentId'] !== null ? ($names[$item['parentId']] ?? null) : null,
			];
		}
		return $menu;
	}

	/**
	 * Checks every item of the menu of a scheme against this instance. Tables and views
	 * are found by uuid and must be manageable by the user, unless the import adds them;
	 * pages are found by technical name. An item that is invalid or whose target is not
	 * here is skipped, with the reason, and so is every item past
	 * {@see MenuItem::MAX_ITEMS_PER_APPLICATION}.
	 *
	 * @param list<mixed> $schemeMenu
	 * @param array<string, true> $addedUuids uuids of the tables and views the import adds
	 * @return array{kept: list<array{input: MenuItemInput, ref: ?string, parent: ?string, entry: array<array-key, mixed>}>, skipped: list<array{index: int, label: mixed, reason: string}>}
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	private function resolveSchemeMenu(Context $context, array $schemeMenu, string $userId, array $addedUuids = []): array {
		$pageIds = [];
		foreach ($context->getPages() ?? [] as $pageId => $page) {
			if (is_string($page['technical_name'] ?? null)) {
				$pageIds[$page['technical_name']] = (int)$pageId;
			}
		}

		$kept = [];
		$skipped = [];
		foreach (array_values($schemeMenu) as $index => $entry) {
			$skip = static function (string $reason) use (&$skipped, $index, $entry): void {
				$skipped[] = ['index' => $index, 'label' => is_array($entry) ? ($entry['label'] ?? null) : null, 'reason' => $reason];
			};
			if (!is_array($entry) || !is_string($entry['targetType'] ?? null)) {
				$skip('it is not an object with a targetType');
				continue;
			}
			$targetId = null;
			if ($entry['targetType'] === MenuItem::TARGET_TABLE || $entry['targetType'] === MenuItem::TARGET_VIEW) {
				$uuid = $entry['targetUuid'] ?? null;
				if (!is_string($uuid)) {
					$skip('its ' . $entry['targetType'] . ' has no uuid');
					continue;
				}
				if (isset($addedUuids[$uuid])) {
					// added by the import, so not here yet; any id passes the check
					$targetId = PHP_INT_MAX;
				} else {
					$node = $entry['targetType'] === MenuItem::TARGET_TABLE ? $this->tableMapper->findByUuid($uuid) : $this->viewMapper->findByUuid($uuid);
					$nodeType = $entry['targetType'] === MenuItem::TARGET_TABLE ? Application::NODE_TYPE_TABLE : Application::NODE_TYPE_VIEW;
					if ($node === null || !$this->permissionsService->canManageNodeById($nodeType, $node->getId(), $userId)) {
						$skip('its ' . $entry['targetType'] . ' is not here or cannot be managed by the user');
						continue;
					}
					$targetId = $node->getId();
				}
			} elseif ($entry['targetType'] === MenuItem::TARGET_PAGE) {
				$targetPage = $entry['targetPage'] ?? null;
				$targetId = is_string($targetPage) ? ($pageIds[$targetPage] ?? null) : null;
				if ($targetId === null) {
					$skip('the application has no page with its technical name');
					continue;
				}
			}
			if (count($kept) >= MenuItem::MAX_ITEMS_PER_APPLICATION) {
				$skip('a menu holds at most ' . MenuItem::MAX_ITEMS_PER_APPLICATION . ' items');
				continue;
			}
			try {
				$input = self::importedMenuInput($entry, $entry['targetType'], $targetId);
			} catch (InvalidArgumentException $e) {
				$skip($e->getMessage());
				continue;
			}
			$kept[] = [
				'input' => $input,
				'ref' => is_string($entry['technicalName'] ?? null) ? $entry['technicalName'] : null,
				'parent' => is_string($entry['parent'] ?? null) ? $entry['parent'] : null,
				'entry' => $entry,
			];
		}
		return ['kept' => $kept, 'skipped' => $skipped];
	}

	/**
	 * The menu of a scheme for an application of this instance, see
	 * {@see self::resolveSchemeMenu()}; every skipped item is logged. A parent is found by
	 * the technical name it was exported under, an automatic `item_<id>` included, and the
	 * menu is nested by {@see self::nestImportedMenu()}.
	 *
	 * @param list<mixed> $schemeMenu
	 * @return list<MenuItemInput>
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	private function importMenu(Context $context, array $schemeMenu, string $userId): array {
		['kept' => $items, 'skipped' => $skipped] = $this->resolveSchemeMenu($context, $schemeMenu, $userId);
		foreach ($skipped as $skip) {
			$this->logger->warning('Skipping menu item ' . $skip['index'] . ' of an imported scheme: ' . $skip['reason']);
		}

		return $this->nestImportedMenu($items, 'scheme menu item');
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

		if (is_array($updateScheme['menuItems'] ?? null)) {
			$changes['menuItems'] = $this->previewMenu($context, $currentScheme, $updateScheme['menuItems'], $updateSchemeTables, array_keys($tablesMap), $userId);
		}

		return $changes;
	}

	/**
	 * The menu an import would save, as the menu of the scheme without the items it would
	 * skip, and the skipped items with the reason.
	 *
	 * @param list<mixed> $schemeMenu
	 * @param array<array-key, mixed> $schemeTables
	 * @param list<string> $localTableUuids
	 * @return array{from: list<array<string, mixed>>, to: list<array<array-key, mixed>>, skipped: list<array{index: int, label: mixed, reason: string}>}
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	private function previewMenu(Context $context, ContextScheme $currentScheme, array $schemeMenu, array $schemeTables, array $localTableUuids, string $userId): array {
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
				if (is_array($view) && is_string($view['uuid'] ?? null) && $this->viewMapper->findByUuid($view['uuid']) === null) {
					$addedUuids[$view['uuid']] = true;
				}
			}
		}
		['kept' => $kept, 'skipped' => $skipped] = $this->resolveSchemeMenu($context, array_values($schemeMenu), $userId, $addedUuids);
		return [
			'from' => $currentScheme->getMenuItems() ?? [],
			'to' => array_column($kept, 'entry'),
			'skipped' => $skipped,
		];
	}

	/**
	 * @param int $contextId
	 * @param string $name
	 * @param string $iconName
	 * @param string $description
	 * @param array $nodes
	 * @param array $tables
	 * @param string $userId
	 * @param list<mixed>|null $menuItems the menu of the scheme; null leaves the menu alone
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
	public function importScheme(int $contextId, string $name, string $iconName, string $description, array $nodes, array $tables, string $userId, ?array $menuItems = null): Context {
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
			if ($table === null) {
				throw new BadRequestError('The table ' . $tableScheme['uuid'] . ' to modify does not exist.');
			}
			$tableService->updateTableStructure($table->getId(), $tableScheme['columns'], $tableScheme['views'], $tableScheme['columnOrder'], $tableScheme['sort'], $userId);
		}

		// Resolve node ids for the new tables and views
		$resolvedNodes = [];
		foreach ($nodes as $node) {
			if ($node['node_type'] === Application::NODE_TYPE_TABLE) {
				$table = $this->tableMapper->findByUuid($node['node_uuid']);
				if ($table === null) {
					$this->logger->warning('Skipping a node of an imported scheme: no table has the uuid ' . $node['node_uuid'] . '.');
					continue;
				}
				$resolvedNodes[] = [
					'id' => $table->getId(),
					'type' => $node['node_type'],
					'permissions' => $node['permissions'],
				];
			} elseif ($node['node_type'] === Application::NODE_TYPE_VIEW) {
				$view = $this->viewMapper->findByUuid($node['node_uuid']);
				if ($view === null) {
					$this->logger->warning('Skipping a node of an imported scheme: no view has the uuid ' . $node['node_uuid'] . '.');
					continue;
				}
				$resolvedNodes[] = [
					'id' => $view->getId(),
					'type' => $node['node_type'],
					'permissions' => $node['permissions'],
				];
			}
		}

		$menu = $menuItems !== null ? $this->importMenu($this->contextMapper->findById($contextId, $userId), $menuItems, $userId) : null;

		return $this->update($contextId, $userId, $name, $iconName, $description, $resolvedNodes, $menu);
	}
}
