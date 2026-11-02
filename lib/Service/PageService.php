<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\Page;
use OCA\Tables\Db\PageContentMapper;
use OCA\Tables\Db\PageMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\ConflictError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\IconHelper;
use OCA\Tables\Helper\TextHelper;
use OCA\Tables\Model\WidgetInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Validation\TechnicalNameValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * The pages of an application and the widgets on them. The controller checks access to
 * the application before any call; this class checks that a page belongs to it.
 *
 * Every write first locks the application, so it runs after a delete of the application
 * or before it. A save then raises the version of its page, which also locks the page,
 * so saves and the delete of a page run one after the other.
 *
 * @psalm-import-type TablesPage from ResponseDefinitions
 * @psalm-import-type TablesWidget from ResponseDefinitions
 */
class PageService {
	use TTransactional;

	private const CHANGED_IN_BETWEEN = 'The page was changed by another save in the meantime. Load it again and retry.';

	public function __construct(
		private readonly ContextMapper $contextMapper,
		private readonly PageMapper $pageMapper,
		private readonly PageContentMapper $pageContentMapper,
		private readonly WidgetService $widgetService,
		private readonly GridWidgetTypes $gridWidgetTypes,
		private readonly IDBConnection $dbc,
	) {
	}

	/**
	 * @return TablesPage
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function find(int $contextId, int $pageId): array {
		$page = $this->getPage($contextId, $pageId);
		return $this->response($page, $this->widgetService->findByPageIds([$pageId])[$pageId] ?? []);
	}

	/**
	 * @return TablesPage
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function findBySlug(int $contextId, string $slug): array {
		try {
			$page = $this->pageMapper->findBySlug($contextId, $slug);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundError('Application ' . $contextId . ' has no page ' . $slug, 0, $e);
		}
		return $this->response($page, $this->widgetService->findByPageIds([$page->getId()])[$page->getId()] ?? []);
	}

	/**
	 * Adds a grid page to an application. Without a slug the page is named
	 * `page_<id>`; a given slug that is taken is refused. The slug never changes.
	 *
	 * @return TablesPage
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function createGridPage(int $contextId, string $title, ?string $slug, ?string $icon, ?string $description): array {
		$page = new Page();
		$page->setContextId($contextId);
		$page->setPageType(Page::TYPE_GRID);
		$page->setTitle($this->validateTitle($title, Page::TYPE_GRID));
		$page->setIcon($this->validateIcon($icon));
		$page->setDescription($this->validateDescription($description));
		if ($slug !== null) {
			$page->setSlug($this->validateSlug($slug));
		}

		$page = $this->save(fn (): Page => $this->atomic(function () use ($contextId, $page): Page {
			$this->lockApplication($contextId);
			return $page->getSlug() === null ? $this->pageMapper->insertWithDefaultName($page) : $this->pageMapper->insert($page);
		}, $this->dbc), $slug);

		return $this->response($page, []);
	}

	/**
	 * Changes the fields of a page and, when widgets are given, makes its widgets match
	 * them in one transaction. A null argument keeps the current value; an empty icon
	 * or description removes it, and so does an empty title of the start page. A
	 * widget's `source` is `{nodeRelId}` of a table or view of this application, or null.
	 * A slug other than the one of the page is refused.
	 *
	 * A save with widgets names the version of the page it was based on, and is refused
	 * when another save raised it since.
	 *
	 * @param list<mixed>|null $widgets
	 * @return TablesPage
	 * @throws BadRequestError
	 * @throws ConflictError
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function update(int $contextId, int $pageId, ?string $title, ?string $slug, ?string $icon, ?string $description, ?array $widgets, ?int $version): array {
		$page = $this->getPage($contextId, $pageId);
		$page->resetUpdatedFields();
		if ($title !== null) {
			$page->setTitle($this->validateTitle($title, $page->getPageType()));
		}
		if ($slug !== null && $slug !== $page->getSlug()) {
			throw new BadRequestError('The slug of a page cannot be changed.');
		}
		if ($icon !== null) {
			$page->setIcon($this->validateIcon($icon));
		}
		if ($description !== null) {
			$page->setDescription($this->validateDescription($description));
		}

		$inputs = null;
		if ($widgets !== null) {
			if ($version === null) {
				throw new BadRequestError('A save of the widgets of a page names the version of the page it is based on.');
			}
			$inputs = $this->readWidgets($widgets, $contextId);
		}

		$stored = $this->save(fn (): ?array => $this->atomic(function () use ($page, $contextId, $pageId, $version, $inputs): ?array {
			$this->lockApplication($contextId);
			if (!$this->pageMapper->raiseVersion($pageId, $version)) {
				$this->getPage($page->getContextId(), $pageId);
				throw new ConflictError(self::CHANGED_IN_BETWEEN);
			}
			$this->pageMapper->update($page);
			$stored = $inputs === null ? null : $this->widgetService->syncPage($pageId, $inputs);
			$page->setVersion($this->pageMapper->findVersion($pageId));
			return $stored;
		}, $this->dbc), null);

		return $this->response($page, $stored ?? $this->widgetService->findByPageIds([$pageId])[$pageId] ?? []);
	}

	/**
	 * Deletes a page with its widgets. The start page cannot be deleted.
	 *
	 * @return TablesPage the deleted page
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws Exception
	 */
	public function delete(int $contextId, int $pageId): array {
		$page = $this->getPage($contextId, $pageId);
		if ($page->getPageType() === Page::TYPE_STARTPAGE) {
			throw new BadRequestError('The start page of an application cannot be deleted.');
		}

		$widgets = $this->atomic(function () use ($contextId, $pageId): array {
			$this->lockApplication($contextId);
			if (!$this->pageMapper->raiseVersion($pageId, null)) {
				throw new NotFoundError('Page ' . $pageId . ' does not belong to application ' . $contextId);
			}
			$widgets = $this->widgetService->findByPageIds([$pageId])[$pageId] ?? [];
			$this->widgetService->deleteByPageIds([$pageId]);
			$this->pageContentMapper->deleteByPageId($pageId);
			$this->pageMapper->deleteByPageId($pageId);
			return $widgets;
		}, $this->dbc);

		return $this->response($page, $widgets);
	}

	/**
	 * @throws NotFoundError
	 * @throws Exception
	 */
	private function getPage(int $contextId, int $pageId): Page {
		try {
			$page = $this->pageMapper->findById($pageId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundError('Page ' . $pageId . ' does not exist', 0, $e);
		}
		if ($page->getContextId() !== $contextId) {
			throw new NotFoundError('Page ' . $pageId . ' does not belong to application ' . $contextId);
		}
		if ($page->getPageType() !== Page::TYPE_GRID && $page->getPageType() !== Page::TYPE_STARTPAGE) {
			throw new NotFoundError('Page ' . $pageId . ' is neither a grid page nor a start page');
		}
		return $page;
	}

	/**
	 * Locks the application row until the transaction ends, the first statement of every
	 * write, so writers take application, page and relations in the same order.
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
	 * @param list<TablesWidget> $widgets
	 * @return TablesPage
	 */
	private function response(Page $page, array $widgets): array {
		return $page->jsonSerialize() + ['widgets' => $widgets];
	}

	/**
	 * @param list<mixed> $widgets
	 * @return list<WidgetInput>
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws Exception
	 */
	private function readWidgets(array $widgets, int $contextId): array {
		if (count($widgets) > GridWidgetTypes::MAX_WIDGETS_PER_PAGE) {
			throw new BadRequestError('A page holds at most ' . GridWidgetTypes::MAX_WIDGETS_PER_PAGE . ' widgets.');
		}
		$nodes = $this->contextMapper->findById($contextId, null, false)->getNodes() ?? [];
		$inputs = [];
		$seenIds = [];
		foreach (array_values($widgets) as $index => $widget) {
			$input = $this->gridWidgetTypes->readWidget($index, $widget);
			if ($input->id !== null && isset($seenIds[$input->id])) {
				throw new BadRequestError('Widget ' . $index . ' has the id of another widget in the list.');
			}
			if ($input->id !== null) {
				$seenIds[$input->id] = true;
			}
			if ($input->nodeRelId !== null && !isset($nodes[$input->nodeRelId])) {
				throw new BadRequestError('The source of widget ' . $index . ' must be {nodeRelId} of a table or view of this application.');
			}
			$inputs[] = $input;
		}
		foreach (array_keys($inputs) as $index) {
			$this->gridWidgetTypes->validateParent($index, $inputs);
		}
		return $inputs;
	}

	/**
	 * Runs a save. A unique index refusing a new slug means the name was
	 * taken. A widget the save lists that is not on the page, or a source that is not
	 * part of the application, is refused: the version check already ruled out a list
	 * built from another state of the page.
	 *
	 * @template T
	 * @param callable(): T $save
	 * @return T
	 * @throws BadRequestError
	 * @throws ConflictError
	 * @throws Exception
	 */
	private function save(callable $save, ?string $newSlug): mixed {
		try {
			return $save();
		} catch (\InvalidArgumentException $e) {
			throw new BadRequestError($e->getMessage(), 0, $e);
		} catch (Exception $e) {
			if ($newSlug === null || $e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			throw new BadRequestError('The slug ' . $newSlug . ' is already used by a page of this application.', 0, $e);
		}
	}

	/**
	 * A grid page needs a title, as its tab shows nothing else; the start page may have
	 * none, and an empty title gives it back its default name.
	 *
	 * @throws BadRequestError
	 */
	private function validateTitle(string $title, string $pageType): ?string {
		try {
			$title = TextHelper::optional($title, TextHelper::TITLE_MAX_LENGTH);
		} catch (\InvalidArgumentException) {
			$title = '';
		}
		if ($title === '' || ($title === null && $pageType !== Page::TYPE_STARTPAGE)) {
			throw new BadRequestError('A page title needs 1 to ' . TextHelper::TITLE_MAX_LENGTH . ' characters.');
		}
		return $title;
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateSlug(string $slug): string {
		if (!TechnicalNameValidator::isValid($slug)) {
			throw new BadRequestError('A slug has 1 to ' . TechnicalNameValidator::MAX_LENGTH . ' lower case letters, digits and underscores and starts with a letter.');
		}
		if (Page::isDefaultSlug($slug)) {
			throw new BadRequestError('The slug ' . $slug . ' is reserved for automatic names.');
		}
		return $slug;
	}

	/**
	 * A named icon, or null for an empty one.
	 *
	 * @throws BadRequestError
	 */
	private function validateIcon(?string $icon): ?string {
		$icon = trim((string)$icon);
		if ($icon === '') {
			return null;
		}
		if (!IconHelper::isValid($icon)) {
			throw new BadRequestError('A page has an unknown icon: ' . $icon);
		}
		return $icon;
	}

	/**
	 * @throws BadRequestError
	 */
	private function validateDescription(?string $description): ?string {
		try {
			return TextHelper::optional($description, TextHelper::DESCRIPTION_MAX_LENGTH);
		} catch (\InvalidArgumentException) {
			throw new BadRequestError('A page description has at most ' . TextHelper::DESCRIPTION_MAX_LENGTH . ' characters.');
		}
	}
}
