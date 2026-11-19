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
use OCA\Tables\Db\ContextNodeRelationMapper;
use OCA\Tables\Db\MenuItem;
use OCA\Tables\Db\MenuItemMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\ConflictError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\Helper\TextHelper;
use OCA\Tables\Model\MenuOrderEntry;
use OCA\Tables\Model\MenuTarget;
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
 * The controller checks access to the application before a read and manage
 * permissions on it before a write; this class checks that an item belongs to it.
 *
 * @psalm-import-type TablesMenuItem from ResponseDefinitions
 * @psalm-type TablesMenuExportItem = array{parent: ?int, title: ?string, displayTitle: string, icon: ?string, description: ?string, target: array{page: int}|array{table: string}|array{view: string}|array{url: string}|null}
 */
class MenuService {
	use TTransactional;

	private const ITEM = 'a menu item';
	private const CHANGED_IN_BETWEEN = 'The menu was changed by another save in the meantime. Load it again and retry.';

	public function __construct(
		private readonly ContextMapper $contextMapper,
		private readonly ContextNodeRelationMapper $contextNodeRelMapper,
		private readonly MenuItemMapper $menuItemMapper,
		private readonly PermissionsService $permissionsService,
		private readonly TableMapper $tableMapper,
		private readonly ViewMapper $viewMapper,
		private readonly IDBConnection $dbc,
	) {
	}

	/**
	 * The menu of an application in display order, with the version of its order that
	 * {@see self::saveOrder()} expects. Readers who cannot manage the application do not
	 * see empty groups.
	 *
	 * @return array{menu: list<TablesMenuItem>, version: string}
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function findAll(int $contextId, string $userId): array {
		$context = $this->contextMapper->findById($contextId, $userId);
		$items = $this->visibleItems($context, $this->menuItemMapper->findByContextId($contextId));
		$menu = $this->serialize($context, $items);
		if (!$this->permissionsService->canManageContext($context, $userId)) {
			$parentIds = array_flip(array_filter(array_column($menu, 'parentId')));
			$menu = array_values(array_filter($menu, static fn (array $item): bool => $item['target'] !== null || isset($parentIds[$item['id']])));
		}
		return ['menu' => $menu, 'version' => $this->version($items)];
	}

	/**
	 * Adds an item to the end of the menu. Without a target the item is a group, which
	 * needs a title. The target is checked in the transaction that saves the item. A
	 * menu without any item first gets the one that opens the start page, so the new
	 * item never replaces it.
	 *
	 * @return TablesMenuItem
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function create(int $contextId, ?string $title, ?string $icon, ?string $description, ?MenuTarget $target, string $userId): array {
		return $this->atomic(function () use ($contextId, $title, $icon, $description, $target, $userId): array {
			$this->lockApplication($contextId);
			$this->lockTarget($target);
			$context = $this->contextMapper->findById($contextId, $userId);
			$items = $this->menuItemMapper->findByContextId($contextId);
			$startPageId = $this->startPageId($context);
			if ($items === [] && $startPageId !== null) {
				$items[] = $this->menuItemMapper->insert(MenuItem::forStartPage($contextId, $startPageId));
			}
			if (count($items) >= MenuItem::MAX_ITEMS_PER_APPLICATION) {
				throw new BadRequestError('A menu holds at most ' . MenuItem::MAX_ITEMS_PER_APPLICATION . ' items.');
			}

			$item = $this->newItem($context, TextHelper::title($title, self::ITEM), IconHelper::optional($icon, self::ITEM), TextHelper::description($description, self::ITEM), $target === null ? null : $this->validateTarget($target, $context));
			$item->setOrder($items === [] ? 0 : max(array_map(static fn (MenuItem $item): int => $item->getOrder(), $items)) + 1);

			return $this->serializeOne($context, $this->menuItemMapper->insert($item));
		}, $this->dbc);
	}

	/**
	 * Changes the fields of an item. A null argument keeps the current value; an empty
	 * title, icon or description removes it, and an item without a title shows the title
	 * of what it opens. A target replaces the previous one and is checked in the
	 * transaction that saves it; a group that holds items cannot get one.
	 *
	 * @return TablesMenuItem
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function update(int $contextId, int $itemId, ?string $title, ?string $icon, ?string $description, ?MenuTarget $target, string $userId): array {
		return $this->atomic(function () use ($contextId, $itemId, $title, $icon, $description, $target, $userId): array {
			$this->lockApplication($contextId);
			$this->lockTarget($target);
			[$item, $context] = $this->getItem($contextId, $itemId, $userId);

			if ($title !== null) {
				$item->setTitle(TextHelper::title($title, self::ITEM));
			}
			if ($icon !== null) {
				$item->setIcon(IconHelper::optional($icon, self::ITEM));
			}
			if ($description !== null) {
				$item->setDescription(TextHelper::description($description, self::ITEM));
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

			$this->menuItemMapper->update($item);
			return $this->serializeOne($context, $item);
		}, $this->dbc);
	}

	/**
	 * Deletes an item. The items of a deleted group move to the top level.
	 *
	 * @return TablesMenuItem the deleted item
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function delete(int $contextId, int $itemId, string $userId): array {
		return $this->atomic(function () use ($contextId, $itemId, $userId): array {
			$this->lockApplication($contextId);
			[$item, $context] = $this->getItem($contextId, $itemId, $userId);
			$deleted = $this->serializeOne($context, $item);
			if ($item->isGroup()) {
				$this->menuItemMapper->clearParent($contextId, $item->getId());
			}
			$this->menuItemMapper->delete($item);
			return $deleted;
		}, $this->dbc);
	}

	/**
	 * Saves the order and grouping of the whole menu in one transaction. The list holds
	 * every item exactly once, in display order, each with the id of its group or null.
	 * Only top-level groups hold items, and a group comes before its items. A save based
	 * on another version than the current one is refused, whatever changed in between.
	 *
	 * @param list<MenuOrderEntry> $order
	 * @param string $version the version of the order the list was built from
	 * @return array{menu: list<TablesMenuItem>, version: string} the whole menu
	 * @throws BadRequestError
	 * @throws ConflictError
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function saveOrder(int $contextId, array $order, string $version, string $userId): array {
		return $this->atomic(function () use ($contextId, $order, $version, $userId): array {
			$this->lockApplication($contextId);
			$context = $this->contextMapper->findById($contextId, $userId);
			$visible = $this->visibleItems($context, $this->menuItemMapper->findByContextId($context->getId()));
			if (!hash_equals($this->version($visible), $version)) {
				throw new ConflictError(self::CHANGED_IN_BETWEEN);
			}
			$items = [];
			foreach ($visible as $item) {
				$items[$item->getId()] = $item;
			}

			$parents = [];
			$positions = [];
			foreach ($order as $position => $entry) {
				$id = $entry->id;
				$parentId = $entry->parentId;
				if (!isset($items[$id])) {
					throw new BadRequestError('Menu item ' . $id . ' is not an item of application ' . $context->getId());
				}
				if (array_key_exists($id, $parents)) {
					throw new BadRequestError('Menu item ' . $id . ' is listed twice.');
				}
				$parents[$id] = $parentId;
				$positions[$id] = $position;
			}
			if (count($parents) !== count($items)) {
				throw new BadRequestError('The order lists every item of the menu.');
			}

			foreach ($parents as $id => $parentId) {
				if ($parentId !== null && ($items[$id]->isGroup() || !isset($positions[$parentId]) || $positions[$parentId] > $positions[$id] || !$items[$parentId]->isGroup() || $parents[$parentId] !== null)) {
					throw new BadRequestError('Menu item ' . $id . ' can only sit in a group at the top level listed before it, and groups cannot sit in a group.');
				}
			}

			$changed = [];
			foreach ($parents as $id => $parentId) {
				if ($items[$id]->getOrder() !== $positions[$id] || $items[$id]->getParentId() !== $parentId) {
					$changed[$id] = ['order' => $positions[$id], 'parentId' => $parentId];
					$items[$id]->setOrder($positions[$id]);
					$items[$id]->setParentId($parentId);
				}
			}
			$this->menuItemMapper->updatePositions($changed);
			$saved = array_map(static fn (int $id): MenuItem => $items[$id], array_keys($parents));
			return ['menu' => $this->serialize($context, $saved), 'version' => $this->version($saved)];
		}, $this->dbc);
	}

	/**
	 * The menu as an export carries it: a page by its slug, a table or view by
	 * uuid, and the group of an item by the group's position in the exported menu. Each
	 * item keeps the title it shows, for a preview; an import reads only its own title.
	 *
	 * @return list<TablesMenuExportItem>
	 * @throws Exception
	 */
	public function export(Context $context): array {
		$menu = $this->serialize($context, $this->menuItemMapper->findByContextId($context->getId()));
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
				$target = ['page' => (int)$target['pageId']];
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
	 * reads it, and returns the items it skipped. A menu of which no item is kept leaves the
	 * menu as it is. Callers check permissions.
	 *
	 * @param list<mixed> $exported
	 * @param \Closure(string, string): ?int $nodeRelIdOf
	 * @param \Closure(int): ?int $pageIdOf
	 * @return list<array{index: int, title: ?string, reason: string}>
	 * @throws Exception
	 */
	public function import(Context $context, array $exported, \Closure $nodeRelIdOf, \Closure $pageIdOf): array {
		['items' => $items, 'skipped' => $skipped] = $this->readExported($context, $exported, $nodeRelIdOf, $pageIdOf);
		if ($items === []) {
			return $skipped;
		}

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
	 * checks it, and skipped, with the reason, when it fails or when its target did not
	 * come along. Entries past the {@see MenuItem::MAX_ITEMS_PER_APPLICATION}th are not read
	 * and are skipped with one record for all of them. `$pageIdOf`
	 * gives the page of the application for an exported page id, or null; `{pageId}` is a
	 * page of the application itself, see {@see self::withLocalPages()}. `$nodeRelIdOf`
	 * gives the node relation for the kind (`table` or `view`) and uuid, or null.
	 * An item sits in the group at the position it names when that group came before it
	 * and was kept; otherwise it moves to the top.
	 *
	 * @param list<mixed> $exported
	 * @param \Closure(string, string): ?int $nodeRelIdOf
	 * @param \Closure(int): ?int $pageIdOf
	 * @return array{items: list<array{item: MenuItem, entry: array<array-key, mixed>, index: int, parent: ?int}>, skipped: list<array{index: int, title: ?string, reason: string}>}
	 */
	public function readExported(Context $context, array $exported, \Closure $nodeRelIdOf, \Closure $pageIdOf): array {
		$items = [];
		$skipped = [];
		$groups = [];
		$exported = array_values($exported);
		foreach (array_slice($exported, 0, MenuItem::MAX_ITEMS_PER_APPLICATION) as $index => $entry) {
			try {
				if (!is_array($entry)) {
					throw new BadRequestError('It is not an object.');
				}
				$target = ($entry['target'] ?? null) === null ? null : $this->importedTarget($entry['target'], $context, $pageIdOf, $nodeRelIdOf);
				$item = $this->newItem($context, TextHelper::title(self::importedText($entry, 'title'), self::ITEM), IconHelper::optional(self::importedText($entry, 'icon'), self::ITEM), TextHelper::description(self::importedText($entry, 'description'), self::ITEM), $target);
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
		$leftOut = count($exported) - MenuItem::MAX_ITEMS_PER_APPLICATION;
		if ($leftOut > 0) {
			$skipped[] = ['index' => MenuItem::MAX_ITEMS_PER_APPLICATION, 'title' => null, 'reason' => 'A menu holds at most ' . MenuItem::MAX_ITEMS_PER_APPLICATION . ' items. ' . $leftOut . ' more were left out.'];
		}
		return ['items' => $items, 'skipped' => $skipped];
	}

	/**
	 * The pages of an application that the pages of a scheme stand for, by exported id. A
	 * scheme imported into an existing application has no other link between the two: its
	 * start page is the start page of the application, and any other page is the page with
	 * its slug.
	 *
	 * @param array<array-key, mixed> $schemePages
	 * @return array<int, int>
	 */
	public function schemePageIds(Context $context, array $schemePages): array {
		$startPageId = null;
		$pageIdsByName = [];
		foreach ($context->getPages() ?? [] as $pageId => $page) {
			if ($page['page_type'] === Page::TYPE_STARTPAGE) {
				$startPageId ??= (int)$pageId;
			}
			$pageIdsByName[(string)($page['slug'] ?? '')] = (int)$pageId;
		}

		$pageIds = [];
		foreach ($schemePages as $page) {
			if (!is_array($page) || !is_int($page['id'] ?? null)) {
				continue;
			}
			$pageId = ($page['page_type'] ?? null) === Page::TYPE_STARTPAGE ? $startPageId : ($pageIdsByName[(string)($page['slug'] ?? '')] ?? null);
			if ($pageId !== null) {
				$pageIds[$page['id']] = $pageId;
			}
		}
		return $pageIds;
	}

	/**
	 * The menu a scheme import into this application would save, as the preview shows it and
	 * as the import takes it back: page targets point at pages of the application, an item
	 * names its group by the group's position among the kept items, and only tables and
	 * views among the scheme's nodes count as having come along.
	 *
	 * @param list<mixed> $menu
	 * @param array<array-key, mixed> $schemePages
	 * @param array<array-key, mixed> $schemeNodes
	 * @return array{to: list<array<array-key, mixed>>, skipped: list<array{index: int, title: ?string, reason: string}>}
	 */
	public function previewSchemeImport(Context $context, array $menu, array $schemePages, array $schemeNodes): array {
		$schemeUuids = [];
		foreach ($schemeNodes as $node) {
			$kind = ($node['node_type'] ?? null) === Application::NODE_TYPE_VIEW ? 'view' : 'table';
			$schemeUuids[$kind][(string)($node['node_uuid'] ?? '')] = true;
		}
		$pageIds = $this->schemePageIds($context, $schemePages);
		['items' => $items, 'skipped' => $skipped] = $this->readExported(
			$context,
			$menu,
			static fn (string $kind, string $uuid): ?int => isset($schemeUuids[$kind][$uuid]) ? 0 : null,
			static fn (int $pageId): ?int => $pageIds[$pageId] ?? null,
		);
		$positions = array_flip(array_column($items, 'index'));
		$entries = array_map(static fn (array $item): array => ['parent' => $item['parent'] === null ? null : $positions[$item['parent']]] + $item['entry'], $items);
		return ['to' => $this->withLocalPages($entries, $pageIds), 'skipped' => $skipped];
	}

	/**
	 * Points the page targets of exported items at pages of this application, so that they
	 * no longer depend on the scheme they came from.
	 *
	 * @param list<array<array-key, mixed>> $entries
	 * @param array<int, int> $pageIds pages of the application by exported id
	 * @return list<array<array-key, mixed>>
	 */
	public function withLocalPages(array $entries, array $pageIds): array {
		return array_map(static function (array $entry) use ($pageIds): array {
			$exportedId = $entry['target']['page'] ?? null;
			if (is_int($exportedId) && isset($pageIds[$exportedId])) {
				$entry['target'] = ['pageId' => $pageIds[$exportedId]];
			}
			return $entry;
		}, $entries);
	}

	/**
	 * The target of an exported item as the API takes it. `{page}`, `{table}` and `{view}`
	 * are translated by the resolvers, which give pages and node relations of the
	 * application; `{url}` and `{pageId}` are checked like the API checks a target.
	 *
	 * @param \Closure(int): ?int $pageIdOf
	 * @param \Closure(string, string): ?int $nodeRelIdOf
	 * @return array{pageId: int}|array{nodeRelId: int}|array{url: string}
	 * @throws BadRequestError
	 */
	private function importedTarget(mixed $target, Context $context, \Closure $pageIdOf, \Closure $nodeRelIdOf): array {
		if (is_array($target) && count($target) === 1) {
			$kind = (string)array_key_first($target);
			$value = $target[$kind];
			if ($kind === 'page' && is_int($value)) {
				return ['pageId' => $pageIdOf($value) ?? throw new BadRequestError('The application has no page ' . $value . '.')];
			}
			if (($kind === 'table' || $kind === 'view') && is_string($value)) {
				return ['nodeRelId' => $nodeRelIdOf($kind, $value) ?? throw new BadRequestError('Its ' . $kind . ' ' . $value . ' is not part of the application.')];
			}
			if ($kind === 'url' || $kind === 'pageId') {
				return $this->validateTarget(MenuTarget::fromArray($target), $context);
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
	 * Locks the application as the first statement of a menu write, so the menu's rules
	 * are checked on what no other write changes meanwhile, and an item is never added to
	 * an application that is being deleted.
	 *
	 * @throws NotFoundError
	 * @throws Exception
	 */
	private function lockApplication(int $contextId): void {
		if (!$this->contextMapper->lock($contextId)) {
			throw new NotFoundError('Application ' . $contextId . ' does not exist');
		}
	}

	/**
	 * Locks the node relation an item is about to open before the application is read,
	 * so a removal of that table or view either ends first and the item is refused, or
	 * waits and removes the item with it. A page is covered by the application lock,
	 * which its delete takes too.
	 *
	 * @throws Exception
	 */
	private function lockTarget(?MenuTarget $target): void {
		$nodeRelId = $target?->nodeRelId;
		if ($nodeRelId !== null) {
			$this->contextNodeRelMapper->lockByIds([$nodeRelId]);
		}
	}

	/**
	 * An item with its application. An item whose target is gone is not found, as it is
	 * left out of the menu.
	 *
	 * @return array{MenuItem, Context}
	 * @throws NotFoundError
	 * @throws Exception
	 */
	private function getItem(int $contextId, int $itemId, string $userId): array {
		try {
			$item = $this->menuItemMapper->findById($itemId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundError('Menu item ' . $itemId . ' does not exist', 0, $e);
		}
		if ($item->getContextId() !== $contextId) {
			throw new NotFoundError('Menu item ' . $itemId . ' does not exist');
		}
		$context = $this->contextMapper->findById($contextId, $userId);
		if ($this->visibleItems($context, [$item]) === []) {
			throw new NotFoundError('Menu item ' . $itemId . ' does not exist');
		}
		return [$item, $context];
	}

	/**
	 * The version of the order and grouping of the items the menu shows. It changes with
	 * every item added, deleted, moved, or hidden because its target is gone.
	 *
	 * @param list<MenuItem> $items in menu order
	 */
	private function version(array $items): string {
		return md5(json_encode(array_map(static fn (MenuItem $item): array => [$item->getId(), $item->getParentId(), $item->getOrder()], $items), JSON_THROW_ON_ERROR));
	}

	private function startPageId(Context $context): ?int {
		foreach ($context->getPages() ?? [] as $pageId => $page) {
			if (($page['page_type'] ?? null) === Page::TYPE_STARTPAGE) {
				return (int)$pageId;
			}
		}
		return null;
	}

	/**
	 * @param list<MenuItem> $items
	 * @return list<MenuItem> the items the menu shows, see {@see self::displayTitles()}
	 * @throws Exception
	 */
	private function visibleItems(Context $context, array $items): array {
		$titles = $this->displayTitles($context, $items);
		return array_values(array_filter($items, static fn (MenuItem $item): bool => isset($titles[$item->getId()])));
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
	 * @param list<MenuItem> $items
	 * @return list<TablesMenuItem> the items the menu shows, see {@see self::displayTitles()}
	 * @throws Exception
	 */
	private function serialize(Context $context, array $items): array {
		$titles = $this->displayTitles($context, $items);
		$menu = [];
		foreach ($items as $item) {
			if (!isset($titles[$item->getId()])) {
				continue;
			}
			$menu[] = [
				'id' => $item->getId(),
				'contextId' => $item->getContextId(),
				'parentId' => $item->getParentId(),
				'title' => $item->getTitle(),
				'displayTitle' => $titles[$item->getId()],
				'icon' => $item->getIcon(),
				'description' => $item->getDescription(),
				'target' => $item->getTarget(),
			];
		}
		return $menu;
	}

	/**
	 * The title each item the menu shows, by item id: its own, else the title of the page,
	 * table, view or link it opens. A start page without a title shows the name of the
	 * application. The menu leaves out an item whose page, table or view is no longer
	 * part of the application or no longer exists.
	 *
	 * @param list<MenuItem> $items
	 * @return array<int, string>
	 * @throws Exception
	 */
	private function displayTitles(Context $context, array $items): array {
		$pages = $context->getPages() ?? [];
		$nodes = $context->getNodes() ?? [];
		$nodeIds = [Application::NODE_TYPE_TABLE => [], Application::NODE_TYPE_VIEW => []];
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

		$titles = [];
		foreach ($items as $item) {
			$pageId = $item->getPageId();
			$node = $this->nodeOf($item, $nodes);
			$targetTitle = match (true) {
				$pageId !== null => isset($pages[$pageId]) ? ($pages[$pageId]['title'] ?? $context->getName()) : null,
				$item->getNodeRelId() !== null => $node === null ? null : ($nodeTitles[$node['node_type']][$node['node_id']] ?? null),
				default => $item->getUrl() ?? '',
			};
			if ($targetTitle !== null) {
				$titles[$item->getId()] = $item->getTitle() ?? $targetTitle;
			}
		}
		return $titles;
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
	 * @return array{pageId: int}|array{nodeRelId: int}|array{url: string}
	 * @throws BadRequestError
	 */
	private function validateTarget(MenuTarget $target, Context $context): array {
		if (($target->pageId !== null && !isset(($context->getPages() ?? [])[$target->pageId]))
			|| ($target->nodeRelId !== null && !isset(($context->getNodes() ?? [])[$target->nodeRelId]))) {
			throw new BadRequestError('The target of a menu item is {pageId} of a page or {nodeRelId} of a table or view of this application, or {url} of a link.');
		}
		return $target->toArray();
	}

	/**
	 * A new item of the application, checked like every item.
	 *
	 * @param array{pageId: int}|array{nodeRelId: int}|array{url: string}|null $target
	 * @throws BadRequestError
	 */
	private function newItem(Context $context, ?string $title, ?string $icon, ?string $description, ?array $target): MenuItem {
		$item = new MenuItem();
		$item->setContextId($context->getId());
		$item->setTitle($title);
		$item->setIcon($icon);
		$item->setDescription($description);
		if ($target !== null) {
			$item->setTarget($target);
		}
		$this->assertValid($item);
		return $item;
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
}
