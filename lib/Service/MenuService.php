<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * The menu of an application. Every item opens something inside the application, so
 * all readers see the same menu; an item whose target is gone is left out.
 *
 * Reads need access to the application, writes need manage permissions on it.
 *
 * @psalm-import-type TablesMenuItem from ResponseDefinitions
 * @psalm-type TablesMenuExportItem = array{parent: ?int, title: ?string, displayTitle: string, icon: ?string, description: ?string, target: array{page: string}|array{table: string}|array{view: string}|array{url: string}|null}
 */
class MenuService {
	use TTransactional;

	private const CHANGED_IN_BETWEEN = 'The menu was changed by another save in the meantime. Load it again and retry.';

	public function __construct(
		private readonly ContextMapper $contextMapper,
		private readonly MenuItemMapper $menuItemMapper,
		private readonly PermissionsService $permissionsService,
		private readonly TableMapper $tableMapper,
		private readonly ViewMapper $viewMapper,
		private readonly IDBConnection $dbc,
	) {
	}

	/**
	 * The menu of an application in display order. Readers who cannot manage the
	 * application do not see empty groups.
	 *
	 * @return list<TablesMenuItem> in menu order
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function findAll(int $contextId, string $userId): array {
		$context = $this->contextMapper->findById($contextId, $userId);
		$menu = $this->serialize($context, $this->menuItemMapper->findByContextId($contextId));
		if ($this->permissionsService->canManageContext($context, $userId)) {
			return $menu;
		}
		$parentIds = array_flip(array_filter(array_column($menu, 'parentId')));
		return array_values(array_filter($menu, static fn (array $item): bool => $item['target'] !== null || isset($parentIds[$item['id']])));
	}

	/**
	 * Adds an item to the end of the menu. Without a target the item is a group, which
	 * needs a title. The target is checked in the transaction that saves the item.
	 *
	 * @param array<array-key, mixed>|null $target
	 * @return TablesMenuItem
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function create(int $contextId, ?string $title, ?string $icon, ?string $description, ?array $target, string $userId): array {
		return $this->atomic(function () use ($contextId, $title, $icon, $description, $target, $userId): array {
			$context = $this->getManageableContext($contextId, $userId);
			$items = $this->menuItemMapper->findByContextId($contextId);
			if (count($items) >= MenuItem::MAX_ITEMS_PER_APPLICATION) {
				throw new BadRequestError('A menu holds at most ' . MenuItem::MAX_ITEMS_PER_APPLICATION . ' items.');
			}

			$item = new MenuItem();
			$item->setContextId($contextId);
			$item->setOrder($items === [] ? 0 : max(array_map(static fn (MenuItem $item): int => $item->getOrder(), $items)) + 1);
			$item->setTitle($this->validateTitle($title));
			$item->setIcon($this->validateIcon($icon));
			$item->setDescription($this->validateDescription($description));
			if ($target !== null) {
				$item->setTarget($this->validateTarget($target, $context));
			}
			$this->assertValid($item);

			return $this->serializeOne($context, $this->menuItemMapper->insert($item));
		}, $this->dbc);
	}

	/**
	 * Changes the fields of an item. A null argument keeps the current value; an empty
	 * title, icon or description removes it, and an item without a title shows the title
	 * of what it opens. A target replaces the previous one and is checked in the
	 * transaction that saves it; a group that holds items cannot get one.
	 *
	 * @param array<array-key, mixed>|null $target
	 * @return TablesMenuItem
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function update(int $itemId, ?string $title, ?string $icon, ?string $description, ?array $target, string $userId): array {
		return $this->atomic(function () use ($itemId, $title, $icon, $description, $target, $userId): array {
			[$item, $context] = $this->getManageableItem($itemId, $userId);

			if ($title !== null) {
				$item->setTitle($this->validateTitle($title));
			}
			if ($icon !== null) {
				$item->setIcon($this->validateIcon($icon));
			}
			if ($description !== null) {
				$item->setDescription($this->validateDescription($description));
			}
			if ($target !== null) {
				foreach ($this->menuItemMapper->findByContextId($context->getId()) as $other) {
					if ($other->getParentId() === $itemId) {
						throw new BadRequestError('A group that holds items cannot open something. Move its items out first.');
					}
				}
				$item->setTarget($this->validateTarget($target, $context));
			}
			$this->assertValid($item);

			if ($item->getUpdatedFields() !== []) {
				$this->menuItemMapper->update($item);
			}
			return $this->serializeOne($context, $item);
		}, $this->dbc);
	}

	/**
	 * Deletes an item. The items of a deleted group move to the top level.
	 *
	 * @return TablesMenuItem the deleted item
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function delete(int $itemId, string $userId): array {
		[$item, $context] = $this->getManageableItem($itemId, $userId);
		$deleted = $this->serializeOne($context, $item);

		$this->atomic(function () use ($item): void {
			$this->menuItemMapper->clearParent($item->getId());
			$this->menuItemMapper->delete($item);
		}, $this->dbc);

		return $deleted;
	}

	/**
	 * Saves the order and grouping of the whole menu in one transaction. The list holds
	 * every item exactly once, in display order, each with the id of its group or null.
	 * Only top-level groups hold items, and a group comes before its items. A menu that
	 * changed since it was read is refused.
	 *
	 * @param list<mixed> $order
	 * @return list<TablesMenuItem> the whole menu
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function saveOrder(int $contextId, array $order, string $userId): array {
		$context = $this->getManageableContext($contextId, $userId);

		$this->atomic(function () use ($context, $order): void {
			$items = [];
			foreach ($this->visibleItems($context, $this->menuItemMapper->findByContextId($context->getId())) as $item) {
				$items[$item->getId()] = $item;
			}

			$parents = [];
			$positions = [];
			foreach (array_values($order) as $position => $entry) {
				$id = is_array($entry) ? ($entry['id'] ?? null) : null;
				$parentId = is_array($entry) ? ($entry['parentId'] ?? null) : null;
				if (!is_int($id) || ($parentId !== null && !is_int($parentId))) {
					throw new BadRequestError('Entry ' . $position . ' needs the id of an item and the id of its group or null.');
				}
				if (!isset($items[$id])) {
					throw new NotFoundError('Menu item ' . $id . ' is not an item of application ' . $context->getId());
				}
				if (array_key_exists($id, $parents)) {
					throw new BadRequestError('Menu item ' . $id . ' is listed twice.');
				}
				$parents[$id] = $parentId;
				$positions[$id] = $position;
			}
			if (count($parents) !== count($items)) {
				throw new BadRequestError(self::CHANGED_IN_BETWEEN);
			}

			foreach ($parents as $id => $parentId) {
				if ($parentId !== null && ($items[$id]->isGroup() || !isset($positions[$parentId]) || $positions[$parentId] > $positions[$id] || !$items[$parentId]->isGroup() || $parents[$parentId] !== null)) {
					throw new BadRequestError('Menu item ' . $id . ' can only sit in a group at the top level listed before it, and groups cannot sit in a group.');
				}
			}

			$position = 0;
			foreach ($parents as $id => $parentId) {
				$items[$id]->setOrder($position++);
				$items[$id]->setParentId($parentId);
				if ($items[$id]->getUpdatedFields() !== []) {
					$this->menuItemMapper->update($items[$id]);
				}
			}
		}, $this->dbc);

		return $this->findAll($contextId, $userId);
	}

	/**
	 * The menu as an export carries it: a page by its technical name, a table or view by
	 * uuid, and the group of an item by the group's position in the exported menu. Each
	 * item keeps the title it shows, for a preview; an import reads only its own title.
	 *
	 * @return list<TablesMenuExportItem>
	 * @throws Exception
	 */
	public function export(Context $context): array {
		$menu = $this->serialize($context, $this->menuItemMapper->findByContextId($context->getId()));
		$pages = $context->getPages() ?? [];
		$nodes = $context->getNodes() ?? [];
		$nodeIds = [Application::NODE_TYPE_TABLE => [], Application::NODE_TYPE_VIEW => []];
		foreach ($menu as $item) {
			$node = isset($item['target']['nodeRelId']) ? $nodes[$item['target']['nodeRelId']] : null;
			if ($node !== null) {
				$nodeIds[$node['node_type']][] = $node['node_id'];
			}
		}
		$nodesById = [
			Application::NODE_TYPE_TABLE => $this->tableMapper->findMany($nodeIds[Application::NODE_TYPE_TABLE]),
			Application::NODE_TYPE_VIEW => $this->viewMapper->findMany($nodeIds[Application::NODE_TYPE_VIEW]),
		];
		$positions = [];
		$exported = [];
		foreach ($menu as $item) {
			$target = $item['target'];
			if (isset($target['pageId'])) {
				$target = ['page' => (string)$pages[$target['pageId']]['technical_name']];
			} elseif (isset($target['nodeRelId'])) {
				$node = $nodes[$target['nodeRelId']];
				$entity = $nodesById[$node['node_type']][$node['node_id']] ?? null;
				if ($entity === null) {
					continue;
				}
				$target = [$node['node_type'] === Application::NODE_TYPE_TABLE ? 'table' : 'view' => (string)$entity->getUuid()];
			}
			$positions[$item['id']] = count($exported);
			$exported[] = [
				'parent' => $item['parentId'] === null ? null : ($positions[$item['parentId']] ?? null),
				'title' => $item['title'],
				'displayTitle' => $item['displayTitle'],
				'icon' => $item['icon'],
				'description' => $item['description'],
				'target' => $target,
			];
		}
		return $exported;
	}

	/**
	 * Replaces the menu of an application with an exported one, as {@see self::readExported()}
	 * reads it, and returns the items it skipped. Callers check permissions.
	 *
	 * @param list<mixed> $exported
	 * @param \Closure(string, string): ?int $nodeRelIdOf
	 * @return list<array{index: int, title: ?string, reason: string}>
	 * @throws Exception
	 */
	public function import(Context $context, array $exported, \Closure $nodeRelIdOf): array {
		['items' => $items, 'skipped' => $skipped] = $this->readExported($context, $exported, $nodeRelIdOf);

		$this->atomic(function () use ($context, $items): void {
			$this->menuItemMapper->deleteByContextId($context->getId());
			$idsByIndex = [];
			foreach ($items as $position => ['item' => $item, 'index' => $index, 'parent' => $parent]) {
				$item->setOrder($position);
				$item->setParentId($parent === null ? null : $idsByIndex[$parent]);
				$idsByIndex[$index] = $this->menuItemMapper->insert($item)->getId();
			}
		}, $this->dbc);

		return $skipped;
	}

	/**
	 * Reads an exported menu for an application. Every item is checked like the API
	 * checks it, and skipped, with the reason, when it fails, when its target did not
	 * come along, or past the {@see MenuItem::MAX_ITEMS_PER_APPLICATION}th item. A page is
	 * found by its technical name, a table or view by `$nodeRelIdOf`, which gives the node
	 * relation of the application for the kind (`table` or `view`) and uuid, or null.
	 * An item sits in the group at the position it names when that group came before it
	 * and was kept; otherwise it moves to the top.
	 *
	 * @param list<mixed> $exported
	 * @param \Closure(string, string): ?int $nodeRelIdOf
	 * @return array{items: list<array{item: MenuItem, entry: array<array-key, mixed>, index: int, parent: ?int}>, skipped: list<array{index: int, title: ?string, reason: string}>}
	 */
	public function readExported(Context $context, array $exported, \Closure $nodeRelIdOf): array {
		$pageIds = [];
		foreach ($context->getPages() ?? [] as $pageId => $page) {
			$pageIds[(string)$page['technical_name']] = (int)$pageId;
		}

		$items = [];
		$skipped = [];
		$groups = [];
		foreach (array_values($exported) as $index => $entry) {
			try {
				if (!is_array($entry)) {
					throw new BadRequestError('It is not an object.');
				}
				if (count($items) >= MenuItem::MAX_ITEMS_PER_APPLICATION) {
					throw new BadRequestError('A menu holds at most ' . MenuItem::MAX_ITEMS_PER_APPLICATION . ' items.');
				}
				$item = new MenuItem();
				$item->setContextId($context->getId());
				$item->setTitle($this->validateTitle(self::importedText($entry, 'title')));
				$item->setIcon($this->validateIcon(self::importedText($entry, 'icon')));
				$item->setDescription($this->validateDescription(self::importedText($entry, 'description')));
				if (($entry['target'] ?? null) !== null) {
					$item->setTarget($this->importedTarget($entry['target'], $pageIds, $nodeRelIdOf));
				}
				$this->assertValid($item);
			} catch (BadRequestError $e) {
				$skipped[] = ['index' => $index, 'title' => is_array($entry) ? self::importedTitle($entry) : null, 'reason' => $e->getMessage()];
				continue;
			}
			$parent = $entry['parent'] ?? null;
			$parent = !$item->isGroup() && is_int($parent) && isset($groups[$parent]) ? $parent : null;
			if ($item->isGroup()) {
				$groups[$index] = true;
			}
			$items[] = ['item' => $item, 'entry' => $entry, 'index' => $index, 'parent' => $parent];
		}
		return ['items' => $items, 'skipped' => $skipped];
	}

	/**
	 * @param \Closure(string, string): ?int $nodeRelIdOf
	 * @param array<string, int> $pageIds the pages of the application by technical name
	 * @return array{pageId: int}|array{nodeRelId: int}|array{url: string}
	 * @throws BadRequestError
	 */
	private function importedTarget(mixed $target, array $pageIds, \Closure $nodeRelIdOf): array {
		if (is_array($target) && count($target) === 1) {
			$kind = (string)array_key_first($target);
			$value = $target[$kind];
			if ($kind === 'url' && is_string($value)) {
				return ['url' => $this->validateUrl($value)];
			}
			if ($kind === 'page' && is_string($value)) {
				return isset($pageIds[$value]) ? ['pageId' => $pageIds[$value]] : throw new BadRequestError('The application has no page ' . $value . '.');
			}
			if (($kind === 'table' || $kind === 'view') && is_string($value)) {
				return ['nodeRelId' => $nodeRelIdOf($kind, $value) ?? throw new BadRequestError('Its ' . $kind . ' ' . $value . ' is not part of the application.')];
			}
		}
		throw new BadRequestError('Its target is not {page}, {table}, {view} or {url}.');
	}

	/**
	 * A text field of an exported item, or null when it is left out.
	 *
	 * @param array<array-key, mixed> $entry
	 * @throws BadRequestError
	 */
	private static function importedText(array $entry, string $field): ?string {
		$value = $entry[$field] ?? null;
		if ($value !== null && !is_string($value)) {
			throw new BadRequestError('Its ' . $field . ' is not a text.');
		}
		return $value;
	}

	/**
	 * @param array<array-key, mixed> $entry
	 */
	private static function importedTitle(array $entry): ?string {
		$title = $entry['displayTitle'] ?? $entry['title'] ?? null;
		return is_string($title) ? $title : null;
	}

	/**
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	private function getManageableContext(int $contextId, string $userId): Context {
		$context = $this->contextMapper->findById($contextId, $userId);
		if (!$this->permissionsService->canManageContext($context, $userId)) {
			throw new PermissionError(sprintf('User %s cannot manage application %d', $userId, $contextId));
		}
		return $context;
	}

	/**
	 * An item with its application, checked once through that application. An item
	 * whose target is gone is not found, as it is left out of the menu.
	 *
	 * @return array{MenuItem, Context}
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	private function getManageableItem(int $itemId, string $userId): array {
		try {
			$item = $this->menuItemMapper->findById($itemId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundError('Menu item ' . $itemId . ' does not exist', 0, $e);
		}
		$context = $this->getManageableContext($item->getContextId(), $userId);
		if ($this->visibleItems($context, [$item]) === []) {
			throw new NotFoundError('Menu item ' . $itemId . ' does not exist');
		}
		return [$item, $context];
	}

	/**
	 * @param list<MenuItem> $items
	 * @return list<MenuItem> the items whose target is a page, table or view of the application, a link, or none
	 */
	private function visibleItems(Context $context, array $items): array {
		$pages = $context->getPages() ?? [];
		$nodes = $context->getNodes() ?? [];
		return array_values(array_filter($items, static fn (MenuItem $item): bool
			=> ($item->getPageId() === null || isset($pages[$item->getPageId()]))
			&& ($item->getNodeRelId() === null || isset($nodes[$item->getNodeRelId()]))));
	}

	/**
	 * @return TablesMenuItem
	 * @throws NotFoundError
	 * @throws Exception
	 */
	private function serializeOne(Context $context, MenuItem $item): array {
		return $this->serialize($context, [$item])[0] ?? throw new NotFoundError('Menu item ' . $item->getId() . ' does not exist');
	}

	/**
	 * The visible items with the title they show: their own, else the title of the page,
	 * table, view or link they open. A start page without a title shows the name of the
	 * application.
	 *
	 * @param list<MenuItem> $items
	 * @return list<TablesMenuItem>
	 * @throws Exception
	 */
	private function serialize(Context $context, array $items): array {
		$pages = $context->getPages() ?? [];
		$nodes = $context->getNodes() ?? [];
		$nodeIds = [Application::NODE_TYPE_TABLE => [], Application::NODE_TYPE_VIEW => []];
		$items = $this->visibleItems($context, $items);
		foreach ($items as $item) {
			$node = $this->nodeOf($item, $nodes);
			if ($node !== null) {
				$nodeIds[$node['node_type']][] = $node['node_id'];
			}
		}
		$nodeTitles = [
			Application::NODE_TYPE_TABLE => $this->tableMapper->findIdToTitleMap($nodeIds[Application::NODE_TYPE_TABLE]),
			Application::NODE_TYPE_VIEW => $this->viewMapper->findIdToTitleMap($nodeIds[Application::NODE_TYPE_VIEW]),
		];

		$menu = [];
		foreach ($items as $item) {
			$node = $this->nodeOf($item, $nodes);
			$targetTitle = match (true) {
				$item->getPageId() !== null => $pages[$item->getPageId()]['title'] ?? $context->getName(),
				$node !== null => $nodeTitles[$node['node_type']][$node['node_id']] ?? null,
				default => $item->getUrl() ?? '',
			};
			if ($targetTitle === null) {
				continue;
			}
			$menu[] = [
				'id' => $item->getId(),
				'contextId' => $item->getContextId(),
				'parentId' => $item->getParentId(),
				'title' => $item->getTitle(),
				'displayTitle' => $item->getTitle() ?? $targetTitle,
				'icon' => $item->getIcon(),
				'description' => $item->getDescription(),
				'target' => $item->getTarget(),
			];
		}
		return $menu;
	}

	/**
	 * @param array<array-key, mixed> $nodes the nodes of the application by relation id
	 * @return array{node_id: int, node_type: int}|null
	 */
	private function nodeOf(MenuItem $item, array $nodes): ?array {
		$nodeRelId = $item->getNodeRelId();
		return $nodeRelId === null ? null : ($nodes[$nodeRelId] ?? null);
	}

	/**
	 * @param array<array-key, mixed> $target
	 * @return array{pageId: int}|array{nodeRelId: int}|array{url: string}
	 * @throws BadRequestError
	 */
	private function validateTarget(array $target, Context $context): array {
		if (count($target) === 1) {
			$pageId = $target['pageId'] ?? null;
			if (is_int($pageId) && isset(($context->getPages() ?? [])[$pageId])) {
				return ['pageId' => $pageId];
			}
			$nodeRelId = $target['nodeRelId'] ?? null;
			if (is_int($nodeRelId) && isset(($context->getNodes() ?? [])[$nodeRelId])) {
				return ['nodeRelId' => $nodeRelId];
			}
			$url = $target['url'] ?? null;
			if (is_string($url)) {
				return ['url' => $this->validateUrl($url)];
			}
		}
		throw new BadRequestError('The target of a menu item is {pageId} of a page or {nodeRelId} of a table or view of this application, or {url} of a link.');
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateUrl(string $url): string {
		$url = trim($url);
		$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
		if (strlen($url) > MenuItem::URL_MAX_LENGTH || filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
			throw new BadRequestError('A link is an http or https address of at most ' . MenuItem::URL_MAX_LENGTH . ' characters.');
		}
		return $url;
	}

	/**
	 * An item opens at most one thing, and a group needs a title.
	 *
	 * @throws BadRequestError
	 */
	private function assertValid(MenuItem $item): void {
		$targets = array_filter([$item->getPageId(), $item->getNodeRelId(), $item->getUrl()], static fn (mixed $target): bool => $target !== null);
		if (count($targets) > 1) {
			throw new BadRequestError('A menu item opens a page, a table or view, or a link, not more than one.');
		}
		if ($item->isGroup() && $item->getTitle() === null) {
			throw new BadRequestError('A group needs a title.');
		}
	}

	/**
	 * A title, or null for an empty one.
	 *
	 * @throws BadRequestError
	 */
	private function validateTitle(?string $title): ?string {
		$title = trim((string)$title);
		if (mb_strlen($title) > MenuItem::TITLE_MAX_LENGTH) {
			throw new BadRequestError('A menu item title has at most ' . MenuItem::TITLE_MAX_LENGTH . ' characters.');
		}
		return $title === '' ? null : $title;
	}

	/**
	 * A named icon, or null for an empty one.
	 *
	 * @throws BadRequestError
	 */
	private function validateIcon(?string $icon): ?string {
		$icon = trim((string)$icon);
		if ($icon !== '' && !IconHelper::isValid($icon)) {
			throw new BadRequestError('A menu item has an unknown icon: ' . $icon);
		}
		return $icon === '' ? null : $icon;
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateDescription(?string $description): ?string {
		$description = trim((string)$description);
		if (mb_strlen($description) > MenuItem::DESCRIPTION_MAX_LENGTH) {
			throw new BadRequestError('A menu item description has at most ' . MenuItem::DESCRIPTION_MAX_LENGTH . ' characters.');
		}
		return $description === '' ? null : $description;
	}
}
