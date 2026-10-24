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
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Validation\TechnicalNameValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * The pages of an application and the widgets on them.
 *
 * Reads need access to the application, writes need manage permissions on it.
 *
 * @psalm-import-type TablesPage from ResponseDefinitions
 * @psalm-import-type TablesWidgetValue from ResponseDefinitions
 * @psalm-type TablesWidgetInput = array{id: ?int, type: string, title: ?string, icon: ?string, description: ?string, position: array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}, settings: array<string, TablesWidgetValue>, nodeRelId: ?int, nodeKey: ?string}
 */
class PageService {
	use TTransactional;

	private const CHANGED_IN_BETWEEN = 'The page was changed by another save in the meantime. Load it again and retry.';

	public function __construct(
		private readonly ContextMapper $contextMapper,
		private readonly PageMapper $pageMapper,
		private readonly PageContentMapper $pageContentMapper,
		private readonly ContextService $contextService,
		private readonly WidgetService $widgetService,
		private readonly PermissionsService $permissionsService,
		private readonly GridWidgetTypes $gridWidgetTypes,
		private readonly IDBConnection $dbc,
	) {
	}

	/**
	 * @return TablesPage
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function find(int $contextId, int $pageId, string $userId): array {
		$context = $this->contextMapper->findById($contextId, $userId);
		$page = $this->getPageArray($context, $pageId);
		$widgets = $this->widgetService->findByPageIds([$pageId]);

		return [
			'id' => $pageId,
			'contextId' => $contextId,
			'pageType' => (string)$page['page_type'],
			'technicalName' => (string)$page['technical_name'],
			'title' => $page['title'] ?? null,
			'icon' => $page['icon'] ?? null,
			'description' => $page['description'] ?? null,
			'widgets' => $widgets[$pageId] ?? [],
		];
	}

	/**
	 * Adds a grid page to an application. Without a technical name the page is named
	 * `page_<id>`; a given technical name that is taken is refused.
	 *
	 * @return TablesPage
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function create(int $contextId, string $title, ?string $technicalName, ?string $icon, ?string $description, string $userId): array {
		$context = $this->getManageableContext($contextId, $userId);

		$page = new Page();
		$page->setContextId($contextId);
		$page->setPageType(Page::TYPE_GRID);
		$page->setTitle($this->validateTitle($title));
		$page->setIcon($this->validateIcon($icon));
		$page->setDescription($this->validateDescription($description));

		if ($technicalName !== null) {
			$page->setTechnicalName($this->validateTechnicalName($technicalName, $context, null));
			$this->saveNamed(fn () => $this->pageMapper->insert($page), $technicalName);
		} else {
			$page = $this->pageMapper->insertWithDefaultName($page);
		}

		return $this->find($contextId, $page->getId(), $userId);
	}

	/**
	 * Changes the fields of a page and, when widgets are given, makes its widgets match
	 * them in one transaction. A null argument keeps the current value; an empty icon
	 * or description removes it. A new technical name must be valid and free in the
	 * application; the title does not change it.
	 *
	 * A widget has a `type`, an optional `id` of a widget of this page, a `title`,
	 * `gridX`, `gridY`, `gridWidth` and `gridHeight` on a grid of 12 columns and
	 * `settings` following the schema of its type. A widget of a type with a source has
	 * a `source`: `{nodeRelId}` of a node of this application, `{nodeType, nodeId}` of a
	 * table or view that is added to the application, or null.
	 *
	 * @param list<mixed>|null $widgets
	 * @return TablesPage
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function update(int $contextId, int $pageId, ?string $title, ?string $technicalName, ?string $icon, ?string $description, ?array $widgets, string $userId): array {
		$context = $this->getManageableContext($contextId, $userId);
		$pageArray = $this->getPageArray($context, $pageId);

		$page = $this->pageMapper->findById($pageId);
		if ($title !== null) {
			$page->setTitle($this->validateTitle($title));
		}
		if ($technicalName !== null && $technicalName !== $page->getTechnicalName()) {
			$page->setTechnicalName($this->validateTechnicalName($technicalName, $context, $pageId));
		}
		if ($icon !== null) {
			$page->setIcon($this->validateIcon($icon));
		}
		if ($description !== null) {
			$page->setDescription($this->validateDescription($description));
		}

		$rows = null;
		$nodesToAdd = [];
		if ($widgets !== null) {
			if ($pageArray['page_type'] !== Page::TYPE_GRID) {
				throw new BadRequestError('Only grid pages hold widgets.');
			}
			[$rows, $nodesToAdd] = $this->readWidgets($widgets, $context, $pageId, $userId);
		}

		$renamed = array_key_exists('technicalName', $page->getUpdatedFields());
		$this->saveChanges(fn () => $this->atomic(function () use ($page, $rows, $nodesToAdd, $contextId, $pageId, $userId): void {
			if ($page->getUpdatedFields() !== []) {
				$this->pageMapper->update($page);
			}
			if ($rows === null) {
				return;
			}
			$addedRelIds = [];
			foreach ($nodesToAdd as $key => $node) {
				[$relation] = $this->contextService->addNodeToContextAndStartpage($contextId, $node['nodeId'], $node['nodeType'], Application::PERMISSION_READ, 100, $userId);
				$addedRelIds[$key] = $relation->getId();
			}
			$this->widgetService->syncPage($pageId, array_map(static function (array $row) use ($addedRelIds): array {
				if ($row['nodeKey'] !== null) {
					$row['nodeRelId'] = $addedRelIds[$row['nodeKey']];
				}
				unset($row['nodeKey']);
				return $row;
			}, $rows));
		}, $this->dbc), $renamed ? (string)$page->getTechnicalName() : null);

		return $this->find($contextId, $pageId, $userId);
	}

	/**
	 * Deletes a grid page with its widgets. The start page cannot be deleted.
	 *
	 * @return TablesPage the deleted page
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function delete(int $contextId, int $pageId, string $userId): array {
		$context = $this->getManageableContext($contextId, $userId);
		$pageArray = $this->getPageArray($context, $pageId);
		if ($pageArray['page_type'] !== Page::TYPE_GRID) {
			throw new BadRequestError('The start page of an application cannot be deleted.');
		}
		$page = $this->find($contextId, $pageId, $userId);

		$this->atomic(function () use ($pageId): void {
			$this->widgetService->deleteByPageIds([$pageId]);
			$this->pageContentMapper->deleteByPageId($pageId);
			$this->pageMapper->deleteByPageId($pageId);
		}, $this->dbc);

		return $page;
	}

	/**
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 */
	private function getManageableContext(int $contextId, string $userId): Context {
		$context = $this->contextMapper->findById($contextId, $userId);
		if (!$this->permissionsService->canManageContextById($contextId, $userId)) {
			throw new PermissionError(sprintf('User %s cannot manage application %d', $userId, $contextId));
		}
		return $context;
	}

	/**
	 * @return array<string, mixed>
	 * @throws NotFoundError
	 */
	private function getPageArray(Context $context, int $pageId): array {
		$pages = $context->getPages() ?? [];
		if (!isset($pages[$pageId])) {
			throw new NotFoundError('Page ' . $pageId . ' does not belong to application ' . $context->getId());
		}
		return $pages[$pageId];
	}

	/**
	 * @param list<mixed> $widgets
	 * @return array{0: list<TablesWidgetInput>, 1: array<string, array{nodeType: int, nodeId: int}>} the widgets, and the nodes to add by node key
	 * @throws BadRequestError
	 * @throws PermissionError
	 * @throws Exception
	 */
	private function readWidgets(array $widgets, Context $context, int $pageId, string $userId): array {
		$existingIds = array_flip($this->widgetService->findIdsByPageId($pageId));
		$relIdsByNodeKey = [];
		foreach ($context->getNodes() ?? [] as $relId => $node) {
			$relIdsByNodeKey[$this->nodeKey((int)$node['node_type'], (int)$node['node_id'])] = (int)$relId;
		}

		if (count($widgets) > GridWidgetTypes::MAX_WIDGETS_PER_PAGE) {
			throw new BadRequestError('A page holds at most ' . GridWidgetTypes::MAX_WIDGETS_PER_PAGE . ' widgets.');
		}
		$rows = [];
		$nodesToAdd = [];
		$seenIds = [];
		foreach (array_values($widgets) as $index => $widget) {
			if (!is_array($widget)) {
				throw new BadRequestError('Widget ' . $index . ' must be an object.');
			}
			$type = $widget['type'] ?? null;
			if (!is_string($type)) {
				throw new BadRequestError('Widget ' . $index . ' needs a type.');
			}
			$typeSchema = $this->gridWidgetTypes->get($type);

			$id = $widget['id'] ?? null;
			if ($id !== null && (!is_int($id) || !isset($existingIds[$id]) || isset($seenIds[$id]))) {
				throw new BadRequestError('Widget ' . $index . ' has the id of no other widget on this page.');
			}
			if ($id !== null) {
				$seenIds[$id] = true;
			}

			$row = [
				'id' => $id,
				'type' => $type,
				'title' => $this->gridWidgetTypes->validateTitle($index, $widget['title'] ?? null),
				'icon' => $this->validateWidgetIcon($index, $widget['icon'] ?? null),
				'description' => $this->gridWidgetTypes->validateDescription($index, $widget['description'] ?? null),
				'position' => $this->gridWidgetTypes->validatePosition($index, $widget),
				'settings' => $this->gridWidgetTypes->sanitizeSettings($type, $widget['settings'] ?? null),
				'nodeRelId' => null,
				'nodeKey' => null,
			];

			$source = $widget['source'] ?? null;
			if ($source !== null) {
				if (!$typeSchema['hasSource']) {
					throw new BadRequestError('Widget ' . $index . ' is a ' . $type . ' widget, which shows no table or view.');
				}
				[$row['nodeRelId'], $newNode] = $this->resolveSource($index, $source, $context, $relIdsByNodeKey);
				if ($newNode !== null) {
					$row['nodeKey'] = $this->nodeKey($newNode['nodeType'], $newNode['nodeId']);
					$nodesToAdd[$row['nodeKey']] = $newNode;
				}
			}
			$rows[] = $row;
		}

		foreach ($nodesToAdd as $node) {
			if (!$this->permissionsService->canManageNodeById($node['nodeType'], $node['nodeId'], $userId)) {
				throw new PermissionError(sprintf('User %s cannot manage node %d (type %d)', $userId, $node['nodeId'], $node['nodeType']));
			}
		}

		return [$rows, $nodesToAdd];
	}

	/**
	 * The node relation of a data widget, or the table or view to add to the application.
	 *
	 * @param array<string, int> $relIdsByNodeKey
	 * @return array{0: ?int, 1: ?array{nodeType: int, nodeId: int}}
	 * @throws BadRequestError
	 */
	private function resolveSource(int $index, mixed $source, Context $context, array $relIdsByNodeKey): array {
		if (!is_array($source)) {
			throw new BadRequestError('The source of widget ' . $index . ' must be an object.');
		}
		if (array_key_exists('nodeRelId', $source)) {
			$nodeRelId = $source['nodeRelId'];
			if (!is_int($nodeRelId) || !isset(($context->getNodes() ?? [])[$nodeRelId])) {
				throw new BadRequestError('Widget ' . $index . ' shows a table or view that is not part of this application.');
			}
			return [$nodeRelId, null];
		}

		$nodeType = $source['nodeType'] ?? null;
		$nodeId = $source['nodeId'] ?? null;
		if (!in_array($nodeType, [Application::NODE_TYPE_TABLE, Application::NODE_TYPE_VIEW], true) || !is_int($nodeId) || $nodeId <= 0) {
			throw new BadRequestError('The source of widget ' . $index . ' needs a nodeRelId of this application, or a nodeType and nodeId of a table or view.');
		}
		$key = $this->nodeKey($nodeType, $nodeId);
		if (isset($relIdsByNodeKey[$key])) {
			return [$relIdsByNodeKey[$key], null];
		}
		return [null, ['nodeType' => $nodeType, 'nodeId' => $nodeId]];
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateTitle(string $title): string {
		$title = trim($title);
		if ($title === '' || mb_strlen($title) > Page::TITLE_MAX_LENGTH) {
			throw new BadRequestError('A page title needs 1 to ' . Page::TITLE_MAX_LENGTH . ' characters.');
		}
		return $title;
	}

	/**
	 * Runs a save of the fields and widgets of a page. A unique index refusing the save
	 * means the new technical name was taken; a widget no longer on the page, or another
	 * refusal of the index, means a concurrent save changed the page in between.
	 *
	 * @throws BadRequestError
	 * @throws Exception
	 */
	private function saveChanges(callable $save, ?string $newTechnicalName): void {
		try {
			$save();
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestError(self::CHANGED_IN_BETWEEN, 0, $e);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			if ($newTechnicalName !== null) {
				throw new BadRequestError('The technical name ' . $newTechnicalName . ' is already used by a page of this application.', 0, $e);
			}
			throw new BadRequestError(self::CHANGED_IN_BETWEEN, 0, $e);
		}
	}

	/**
	 * Runs a save of a page under a technical name that was given, reporting a save the
	 * unique index refused as a taken name.
	 *
	 * @throws BadRequestError
	 * @throws Exception
	 */
	private function saveNamed(callable $save, string $technicalName): void {
		try {
			$save();
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			throw new BadRequestError('The technical name ' . $technicalName . ' is already used by a page of this application.');
		}
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateTechnicalName(string $technicalName, Context $context, ?int $pageId): string {
		if (!TechnicalNameValidator::isValid($technicalName)) {
			throw new BadRequestError('A technical name has 1 to ' . TechnicalNameValidator::MAX_LENGTH . ' lower case letters, digits and underscores and starts with a letter.');
		}
		if (Page::isDefaultName($technicalName) && $technicalName !== Page::DEFAULT_NAME_PREFIX . $pageId) {
			throw new BadRequestError('The technical name ' . $technicalName . ' is reserved for automatic names.');
		}
		foreach ($context->getPages() ?? [] as $id => $page) {
			if ((int)$id !== $pageId && ($page['technical_name'] ?? null) === $technicalName) {
				throw new BadRequestError('The technical name ' . $technicalName . ' is already used by a page of this application.');
			}
		}
		return $technicalName;
	}

	/**
	 * A named icon, or null for an empty one.
	 *
	 * @throws BadRequestError
	 */
	private function validateIcon(?string $icon, string $of = 'A page'): ?string {
		$icon = trim((string)$icon);
		if ($icon === '') {
			return null;
		}
		if (!IconHelper::isValid($icon)) {
			throw new BadRequestError($of . ' has an unknown icon: ' . $icon);
		}
		return $icon;
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateDescription(?string $description): ?string {
		$description = trim((string)$description);
		if (mb_strlen($description) > Page::DESCRIPTION_MAX_LENGTH) {
			throw new BadRequestError('A page description has at most ' . Page::DESCRIPTION_MAX_LENGTH . ' characters.');
		}
		return $description === '' ? null : $description;
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateWidgetIcon(int $index, mixed $icon): ?string {
		if ($icon !== null && !is_string($icon)) {
			throw new BadRequestError('The icon of widget ' . $index . ' must be the name of an icon.');
		}
		return $this->validateIcon($icon, 'Widget ' . $index);
	}

	private function nodeKey(int $nodeType, int $nodeId): string {
		return sprintf('t%di%d', $nodeType, $nodeId);
	}
}
