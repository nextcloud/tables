<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Widget;
use OCA\Tables\Db\WidgetMapper;
use OCA\Tables\Db\WidgetSourceMapper;
use OCA\Tables\Model\WidgetInput;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * Stores the widgets of pages, settings included, and the node a data widget shows.
 * Callers check permissions; {@see self::insertAll()} and {@see self::syncPage()} run
 * in their own transaction.
 *
 * @psalm-import-type TablesWidgetSettingValue from Widget
 * @psalm-type TablesWidgetRecord = array{id: int, type: string, title: ?string, icon: ?string, description: ?string, gridX: int, gridY: int, gridWidth: int, gridHeight: int, settings: \stdClass, source: ?array{nodeRelId: int}}
 */
class WidgetService {
	use \OCP\AppFramework\Db\TTransactional;

	public function __construct(
		private readonly WidgetMapper $widgetMapper,
		private readonly WidgetSourceMapper $widgetSourceMapper,
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @param int[] $pageIds
	 * @return array<int, list<TablesWidgetRecord>> widgets by page id, in grid order
	 * @throws Exception
	 */
	public function findByPageIds(array $pageIds): array {
		$widgets = $this->widgetMapper->findByPageIds($pageIds);
		$widgetIds = array_map(static fn (Widget $widget): int => $widget->getId(), $widgets);
		$sources = $this->widgetSourceMapper->findNodeRelIdsByWidgetIds($widgetIds);

		$byPage = [];
		foreach ($widgets as $widget) {
			$id = $widget->getId();
			$byPage[$widget->getPageId()][] = [
				'id' => $id,
				'type' => $widget->getType(),
				'title' => $widget->getTitle(),
				'icon' => $widget->getIcon(),
				'description' => $widget->getDescription(),
				'gridX' => $widget->getGridX(),
				'gridY' => $widget->getGridY(),
				'gridWidth' => $widget->getGridWidth(),
				'gridHeight' => $widget->getGridHeight(),
				'settings' => $widget->getSettingsObject(),
				'source' => isset($sources[$id]) ? ['nodeRelId' => $sources[$id]] : null,
			];
		}
		return $byPage;
	}

	/**
	 * Adds widgets to a page, in one transaction. Their ids are ignored.
	 *
	 * @param list<WidgetInput> $widgets
	 * @return list<Widget>
	 * @throws \InvalidArgumentException when a source is not a node of the application of the page
	 * @throws Exception
	 * @throws \JsonException
	 */
	public function insertAll(int $pageId, array $widgets): array {
		return $this->atomic(function () use ($pageId, $widgets): array {
			$this->assertSourcesOfPage($pageId, $widgets);
			return array_map(fn (WidgetInput $widget): Widget => $this->insertWidget($pageId, $widget), $widgets);
		}, $this->db);
	}

	/**
	 * @throws Exception
	 * @throws \JsonException
	 */
	private function insertWidget(int $pageId, WidgetInput $input): Widget {
		$widget = new Widget();
		$widget->setPageId($pageId);
		$this->applyFields($widget, $input);
		$widget = $this->widgetMapper->insert($widget);
		if ($input->nodeRelId !== null) {
			$this->widgetSourceMapper->insertSource($widget->getId(), $input->nodeRelId);
		}
		return $widget;
	}

	/**
	 * Makes the widgets of a page match the given list: widgets with an id are updated,
	 * widgets without one are inserted and widgets of the page that are not listed are
	 * deleted, in one transaction. The widgets of the page and the node relations shown
	 * are locked first, so the checks still hold when the writes run.
	 *
	 * @param list<WidgetInput> $widgets
	 * @throws \InvalidArgumentException when an id is not one of a widget of the page, or a
	 *                                   source is not a node of the application of the page
	 * @throws Exception
	 * @throws \JsonException
	 */
	public function syncPage(int $pageId, array $widgets): void {
		$this->atomic(function () use ($pageId, $widgets): void {
			$existing = [];
			foreach ($this->widgetMapper->findByPageIds([$pageId], true) as $widget) {
				$existing[$widget->getId()] = $widget;
			}
			$this->assertSourcesOfPage($pageId, $widgets);
			$listedIds = [];
			foreach ($widgets as $input) {
				if ($input->id === null) {
					continue;
				}
				if (!isset($existing[$input->id])) {
					throw new \InvalidArgumentException('Widget ' . $input->id . ' is not on page ' . $pageId);
				}
				$listedIds[] = $input->id;
			}
			$this->deleteByIds(array_values(array_diff(array_keys($existing), $listedIds)));
			$sources = $this->widgetSourceMapper->findNodeRelIdsByWidgetIds($listedIds);

			foreach ($widgets as $input) {
				if ($input->id === null) {
					$this->insertWidget($pageId, $input);
					continue;
				}
				// A new entity would not mark a field set to its default, such as a null title, as changed.
				$widget = $existing[$input->id];
				$this->applyFields($widget, $input);
				$this->widgetMapper->update($widget);
				if (($sources[$input->id] ?? null) !== $input->nodeRelId) {
					$this->widgetSourceMapper->setSource($input->id, $input->nodeRelId);
				}
			}
		}, $this->db);
	}

	/**
	 * Deletes the widgets of the given pages with their sources.
	 *
	 * @param int[] $pageIds
	 * @throws Exception
	 */
	public function deleteByPageIds(array $pageIds): void {
		$this->deleteByIds(array_map(static fn (Widget $widget): int => $widget->getId(), $this->widgetMapper->findByPageIds($pageIds)));
	}

	/**
	 * @param int[] $widgetIds
	 * @throws Exception
	 */
	public function deleteByIds(array $widgetIds): void {
		$this->widgetSourceMapper->deleteByWidgetIds($widgetIds);
		$this->widgetMapper->deleteByIds($widgetIds);
	}

	/**
	 * Unlinks every widget that shows one of the given node relations. The widgets stay.
	 * The relations stay locked until the caller's transaction ends, so a save can't
	 * point a widget at one of them in between.
	 *
	 * @param int[] $nodeRelIds
	 * @throws Exception
	 */
	public function deleteSourcesByNodeRelIds(array $nodeRelIds): void {
		$this->widgetSourceMapper->lockNodeRelations($nodeRelIds);
		$this->widgetSourceMapper->deleteByNodeRelIds($nodeRelIds);
	}

	/**
	 * @param list<WidgetInput> $widgets
	 * @throws \InvalidArgumentException when one is not a node of the application of the page
	 * @throws Exception
	 */
	private function assertSourcesOfPage(int $pageId, array $widgets): void {
		$nodeRelIds = array_values(array_unique(array_filter(array_map(static fn (WidgetInput $widget): ?int => $widget->nodeRelId, $widgets), static fn (?int $id): bool => $id !== null)));
		$foreign = array_diff($nodeRelIds, $this->widgetSourceMapper->filterNodeRelIdsOfPageContext($pageId, $nodeRelIds));
		if ($foreign !== []) {
			throw new \InvalidArgumentException('Node relation ' . implode(', ', $foreign) . ' is not part of the application of page ' . $pageId);
		}
	}

	/**
	 * @throws \JsonException
	 */
	private function applyFields(Widget $widget, WidgetInput $input): void {
		$widget->setType($input->type);
		$widget->setTitle($input->title);
		$widget->setIcon($input->icon);
		$widget->setDescription($input->description);
		$widget->setGridX($input->gridX);
		$widget->setGridY($input->gridY);
		$widget->setGridWidth($input->gridWidth);
		$widget->setGridHeight($input->gridHeight);
		$widget->setSettingsArray($input->settings);
	}
}
