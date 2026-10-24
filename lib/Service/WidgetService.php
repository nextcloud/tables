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
use OCP\DB\Exception;

/**
 * Stores the widgets of grid pages, settings included, and the node a data widget
 * shows. Callers check permissions and run writes in a transaction.
 *
 * @psalm-import-type TablesWidgetSettingValue from Widget
 * @psalm-type TablesWidgetRecord = array{id: int, type: string, title: ?string, icon: ?string, description: ?string, gridX: int, gridY: int, gridWidth: int, gridHeight: int, settings: \stdClass, source: ?array{nodeRelId: int}}
 */
class WidgetService {
	public function __construct(
		private readonly WidgetMapper $widgetMapper,
		private readonly WidgetSourceMapper $widgetSourceMapper,
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
	 * @param array{gridX: int, gridY: int, gridWidth: int, gridHeight: int} $position
	 * @param array<string, TablesWidgetSettingValue> $settings
	 * @throws \InvalidArgumentException when the source is not a node of the application of the page
	 * @throws Exception
	 * @throws \JsonException
	 */
	public function insert(int $pageId, string $type, ?string $title, array $position, array $settings, ?int $nodeRelId, ?string $icon = null, ?string $description = null): Widget {
		$this->assertSourcesOfPage($pageId, [$nodeRelId]);
		return $this->insertWidget($pageId, $type, $title, $position, $settings, $nodeRelId, $icon, $description);
	}

	/**
	 * @param array{gridX: int, gridY: int, gridWidth: int, gridHeight: int} $position
	 * @param array<string, TablesWidgetSettingValue> $settings
	 * @throws Exception
	 * @throws \JsonException
	 */
	private function insertWidget(int $pageId, string $type, ?string $title, array $position, array $settings, ?int $nodeRelId, ?string $icon, ?string $description): Widget {
		$widget = new Widget();
		$widget->setPageId($pageId);
		$this->applyFields($widget, $type, $title, $position, $settings, $icon, $description);
		$widget = $this->widgetMapper->insert($widget);

		$this->widgetSourceMapper->setSource($widget->getId(), $nodeRelId);
		return $widget;
	}

	/**
	 * Makes the widgets of a page match the given list: widgets with an id are updated,
	 * widgets without one are inserted and widgets of the page that are not listed are
	 * deleted. A widget listed without an icon or description has none.
	 *
	 * @param list<array{id: ?int, type: string, title: ?string, icon?: ?string, description?: ?string, position: array{gridX: int, gridY: int, gridWidth: int, gridHeight: int}, settings: array<string, TablesWidgetSettingValue>, nodeRelId: ?int}> $widgets
	 * @throws \InvalidArgumentException when an id is not one of a widget of the page, or a
	 *                                   source is not a node of the application of the page
	 * @throws Exception
	 * @throws \JsonException
	 */
	public function syncPage(int $pageId, array $widgets): void {
		$this->assertSourcesOfPage($pageId, array_column($widgets, 'nodeRelId'));
		$existing = [];
		foreach ($this->widgetMapper->findByPageIds([$pageId]) as $widget) {
			$existing[$widget->getId()] = $widget;
		}
		$listedIds = [];
		foreach ($widgets as $data) {
			if ($data['id'] === null) {
				continue;
			}
			if (!isset($existing[$data['id']])) {
				throw new \InvalidArgumentException('Widget ' . $data['id'] . ' is not on page ' . $pageId);
			}
			$listedIds[] = $data['id'];
		}
		$this->deleteByIds(array_values(array_diff(array_keys($existing), $listedIds)));

		foreach ($widgets as $data) {
			if ($data['id'] === null) {
				$this->insertWidget($pageId, $data['type'], $data['title'], $data['position'], $data['settings'], $data['nodeRelId'], $data['icon'] ?? null, $data['description'] ?? null);
				continue;
			}
			// A new entity would not mark a field set to its default, such as a null title, as changed.
			$widget = $existing[$data['id']];
			$this->applyFields($widget, $data['type'], $data['title'], $data['position'], $data['settings'], $data['icon'] ?? null, $data['description'] ?? null);
			if ($widget->getUpdatedFields() !== []) {
				$this->widgetMapper->update($widget);
			}
			$this->widgetSourceMapper->setSource($data['id'], $data['nodeRelId']);
		}
	}

	/**
	 * Deletes the widgets of the given pages with their sources.
	 *
	 * @param int[] $pageIds
	 * @throws Exception
	 */
	public function deleteByPageIds(array $pageIds): void {
		$widgetIds = [];
		foreach ($pageIds as $pageId) {
			$widgetIds = array_merge($widgetIds, $this->widgetMapper->findIdsByPageId($pageId));
		}
		$this->deleteByIds($widgetIds);
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
	 *
	 * @param int[] $nodeRelIds
	 * @throws Exception
	 */
	public function deleteSourcesByNodeRelIds(array $nodeRelIds): void {
		$this->widgetSourceMapper->deleteByNodeRelIds($nodeRelIds);
	}

	/**
	 * @param list<?int> $nodeRelIds
	 * @throws \InvalidArgumentException when one is not a node of the application of the page
	 * @throws Exception
	 */
	private function assertSourcesOfPage(int $pageId, array $nodeRelIds): void {
		$nodeRelIds = array_values(array_unique(array_filter($nodeRelIds, static fn (?int $id): bool => $id !== null)));
		$foreign = array_diff($nodeRelIds, $this->widgetSourceMapper->filterNodeRelIdsOfPageContext($pageId, $nodeRelIds));
		if ($foreign !== []) {
			throw new \InvalidArgumentException('Node relation ' . implode(', ', $foreign) . ' is not part of the application of page ' . $pageId);
		}
	}

	/**
	 * @param array{gridX: int, gridY: int, gridWidth: int, gridHeight: int} $position
	 * @param array<string, TablesWidgetSettingValue> $settings
	 * @throws \JsonException
	 */
	private function applyFields(Widget $widget, string $type, ?string $title, array $position, array $settings, ?string $icon, ?string $description): void {
		$widget->setType($type);
		$widget->setTitle($title);
		$widget->setIcon($icon);
		$widget->setDescription($description);
		$widget->setGridX($position['gridX']);
		$widget->setGridY($position['gridY']);
		$widget->setGridWidth($position['gridWidth']);
		$widget->setGridHeight($position['gridHeight']);
		$widget->setSettingsArray($settings);
	}
}
