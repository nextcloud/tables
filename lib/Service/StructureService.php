<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;

class StructureService {

	public function __construct(
		private readonly TableService $tableService,
	) {
	}

	protected array $addedColumns = [];
	protected array $removedColumns = [];
	protected array $modifiedColumns = [];
	protected array $columnOrderChanges = [];
	protected array $sortChanges = [];

	protected array $addedViews = [];
	protected array $removedViews = [];
	protected array $modifiedViews = [];

	/**
	 * Calling this method expects having manage permissions against the
	 * specified table and does not check them any further.
	 *
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function resolveChangesForTable(int $tableId, array $updateSchema): void {
		$currentSchema = $this->tableService->getScheme($tableId);
		$this->resolveChanges($currentSchema->jsonSerialize(), $updateSchema);
	}

	public function resolveChanges(array $currentSchema, array $updateSchema): void {
		$this->resolveColumnChanges($currentSchema, $updateSchema);
		$this->resolveViewChanges($currentSchema, $updateSchema);
		$this->resolveColumnOrderChanges($currentSchema, $updateSchema);
		$this->resolveSortChanges($currentSchema, $updateSchema);
	}

	public function addedColumns(): array {
		return $this->addedColumns;
	}

	public function removedColumns(): array {
		return $this->removedColumns;
	}

	public function modifiedColumns(): array {
		return $this->modifiedColumns;
	}

	public function addedViews(): array {
		return $this->addedViews;
	}

	public function removedViews(): array {
		return $this->removedViews;
	}

	public function modifiedViews(): array {
		return $this->modifiedViews;
	}

	public function columnOrderChanges(): array {
		return $this->columnOrderChanges;
	}

	public function sortChanges(): array {
		return $this->sortChanges;
	}

	protected function resolveColumnChanges(array $currentSchema, array $updateSchema): void {
		$this->addedColumns = [];
		$this->removedColumns = [];
		$this->modifiedColumns = [];

		$existingColumnMap = $this->getColumnMap($currentSchema['columns']);
		$updatedColumnMap = $this->getColumnMap($updateSchema['columns']);

		$this->determineAddedColumns($existingColumnMap, $updatedColumnMap, $updateSchema);
		$this->determineRemovedColumns($existingColumnMap, $updatedColumnMap, $currentSchema);
		$this->determineModifiedColumns($existingColumnMap, $updatedColumnMap, $currentSchema, $updateSchema);
	}

	protected function determineAddedColumns(array $existingColumnMap, array $updatedColumnMap, array $updateSchema): void {
		$columnUuids = array_keys(array_diff_key($updatedColumnMap, $existingColumnMap));
		foreach ($columnUuids as $uuid) {
			$this->addedColumns[$uuid] = $updateSchema['columns'][$updatedColumnMap[$uuid]['arrayIndex']];
		}
	}

	protected function determineRemovedColumns(array $existingColumnMap, array $updatedColumnMap, array $currentSchema): void {
		$columnUuids = array_keys(array_diff_key($existingColumnMap, $updatedColumnMap));
		foreach ($columnUuids as $uuid) {
			$this->removedColumns[$uuid] = $currentSchema['columns'][$existingColumnMap[$uuid]['arrayIndex']];
		}
	}

	protected function determineModifiedColumns(array $existingColumnMap, array $updatedColumnMap, array $currentSchema, array $updateSchema): void {
		$updatedColumnUuids = array_keys(array_intersect_key($existingColumnMap, $updatedColumnMap));

		$updatedColumnUuids = array_filter(
			$updatedColumnUuids,
			function (string $uuid) use ($currentSchema, $updateSchema, $existingColumnMap, $updatedColumnMap): bool {
				$columnCurrent = $currentSchema['columns'][$existingColumnMap[$uuid]['arrayIndex']];
				$columnNew = $updateSchema['columns'][$updatedColumnMap[$uuid]['arrayIndex']];
				return $this->isColumnModified($columnNew, $columnCurrent);
			}
		);

		foreach ($updatedColumnUuids as $uuid) {
			$this->modifiedColumns[$uuid] = [
				'from' => $currentSchema['columns'][$existingColumnMap[$uuid]['arrayIndex']],
				'to' => $updateSchema['columns'][$updatedColumnMap[$uuid]['arrayIndex']],
			];
		}
	}

	protected function isColumnModified($columnA, $columnB): bool {
		if ($columnA instanceof \OCA\Tables\Db\Column) {
			$columnA = $columnA->jsonSerialize();
		}
		if ($columnB instanceof \OCA\Tables\Db\Column) {
			$columnB = $columnB->jsonSerialize();
		}
		unset(
			$columnA['id'], $columnB['id'],
			$columnA['createdBy'], $columnB['createdBy'],
			$columnA['createdByDisplayName'], $columnB['createdByDisplayName'],
			$columnA['createdAt'], $columnB['createdAt'],
			$columnA['lastEditBy'], $columnB['lastEditBy'],
			$columnA['lastEditByDisplayName'], $columnB['lastEditByDisplayName'],
			$columnA['lastEditAt'], $columnB['lastEditAt'],
			$columnA['tableId'], $columnB['tableId'],
		);
		$jsonColumnA = json_encode($columnA);
		$jsonColumnB = json_encode($columnB);
		// cannot use array_diff_assoc() is it does not deal with nested arrays
		return $jsonColumnA !== $jsonColumnB;
	}

	protected function getColumnMap(array $currentSchemaColumns): array {
		$map = [];
		foreach ($currentSchemaColumns as $i => $column) {
			if ($column instanceof \OCA\Tables\Db\Column) {
				$column = $column->jsonSerialize();
			}
			$map[$column['uuid']] = [
				'arrayIndex' => $i,
				'id' => $column['id'],
			];
		}
		return $map;
	}

	protected function getColumnsMapById(array $currentSchemaColumns): array {
		$map = [];
		foreach ($currentSchemaColumns as $column) {
			if ($column instanceof \OCA\Tables\Db\Column) {
				$column = $column->jsonSerialize();
			}
			$map[$column['id']] = $column;
		}
		return $map;
	}

	protected function resolveViewChanges(array $currentSchema, array $updateSchema): void {
		$this->addedViews = [];
		$this->removedViews = [];
		$this->modifiedViews = [];

		$existingViewMap = $this->getViewMap($currentSchema['views']);
		$existingColumnMap = $this->getColumnsMapById($currentSchema['columns']);

		$updatedViewMap = $this->getViewMap($updateSchema['views']);
		$updateColumnsMap = $this->getColumnsMapById($updateSchema['columns']);

		$currentSchema['views'] = $this->prepareViewsData($currentSchema['views'], $existingColumnMap);
		$updateSchema['views'] = $this->prepareViewsData($updateSchema['views'], $updateColumnsMap);

		$this->determineAddedViews($existingViewMap, $updatedViewMap, $updateSchema);
		$this->determineRemovedViews($existingViewMap, $updatedViewMap, $currentSchema);
		$this->determineModifiedViews($existingViewMap, $updatedViewMap, $currentSchema, $updateSchema);
	}

	protected function prepareViewsData(array $views, array $columnsMap): array {
		foreach ($views as $i => $view) {
			if ($view instanceof \OCA\Tables\Db\View) {
				$view = $view->jsonSerialize();
				$views[$i] = $view;
			}
			foreach ($view['columnSettings'] as $j => $col) {
				if ($col instanceof ViewColumnInformation) {
					$col = $col->jsonSerialize();
					$views[$i]['columnSettings'][$j] = $col;
				}
				if (isset($columnsMap[$col['columnId']])) {
					$views[$i]['columnSettings'][$j]['columnUuid'] = $columnsMap[$col['columnId']]['uuid'];
					$views[$i]['columnSettings'][$j]['columnTitle'] = $columnsMap[$col['columnId']]['title'];
					unset($views[$i]['columnSettings'][$j]['columnId']);
				}
			}
			foreach ($view['sort'] as $j => $col) {
				if (isset($columnsMap[$col['columnId']])) {
					$views[$i]['sort'][$j]['columnUuid'] = $columnsMap[$col['columnId']]['uuid'];
					unset($views[$i]['sort'][$j]['columnId']);
				}
			}
			foreach ($view['filter'] as $j => $filterGroup) {
				foreach ($filterGroup as $k => $col) {
					if (isset($columnsMap[$col['columnId']])) {
						$views[$i]['filter'][$j][$k]['columnUuid'] = $columnsMap[$col['columnId']]['uuid'];
						unset($views[$i]['filter'][$j][$k]['columnId']);
					}
				}
			}
		}
		return $views;
	}

	protected function getViewMap(array $currentSchemaViews): array {
		$map = [];
		foreach ($currentSchemaViews as $i => $view) {
			if ($view instanceof \OCA\Tables\Db\View) {
				$view = $view->jsonSerialize();
			}
			$map[$view['uuid']] = [
				'arrayIndex' => $i,
				'id' => $view['id'],
			];
		}
		return $map;
	}

	protected function determineAddedViews(array $existingViewMap, array $updatedViewMap, array $updateSchema): void {
		$viewUuids = array_keys(array_diff_key($updatedViewMap, $existingViewMap));
		foreach ($viewUuids as $uuid) {
			$this->addedViews[$uuid] = $updateSchema['views'][$updatedViewMap[$uuid]['arrayIndex']];
		}
	}

	protected function determineRemovedViews(array $existingViewMap, array $updatedViewMap, array $currentSchema): void {
		$viewUuids = array_keys(array_diff_key($existingViewMap, $updatedViewMap));
		foreach ($viewUuids as $uuid) {
			$this->removedViews[$uuid] = $currentSchema['views'][$existingViewMap[$uuid]['arrayIndex']];
		}
	}

	protected function determineModifiedViews(array $existingViewMap, array $updatedViewMap, array $currentSchema, array $updateSchema): void {
		$updatedViewUuids = array_keys(array_intersect_key($existingViewMap, $updatedViewMap));

		$updatedViewUuids = array_filter(
			$updatedViewUuids,
			function (string $uuid) use ($currentSchema, $updateSchema, $existingViewMap, $updatedViewMap): bool {
				$viewCurrent = $currentSchema['views'][$existingViewMap[$uuid]['arrayIndex']];
				$viewNew = $updateSchema['views'][$updatedViewMap[$uuid]['arrayIndex']];
				return $this->isViewModified($viewNew, $viewCurrent);
			}
		);

		foreach ($updatedViewUuids as $uuid) {
			$this->modifiedViews[$uuid] = [
				'from' => $currentSchema['views'][$existingViewMap[$uuid]['arrayIndex']],
				'to' => $updateSchema['views'][$updatedViewMap[$uuid]['arrayIndex']],
			];
		}
	}

	protected function isViewModified($viewA, $viewB): bool {
		if ($viewA instanceof \OCA\Tables\Db\View) {
			$viewA = $viewA->jsonSerialize();
		}
		if ($viewB instanceof \OCA\Tables\Db\View) {
			$viewB = $viewB->jsonSerialize();
		}

		$jsonViewA = json_encode(
			[
				'title' => $viewA['title'],
				'technicalName' => $viewA['technicalName'],
				'description' => $viewA['description'],
				'emoji' => $viewA['emoji'],
				'columns' => $viewA['columns'],
				'columnSettings' => $viewA['columnSettings'],
				'sort' => $viewA['sort'],
				'filter' => $viewA['filter'],
				'layout' => $viewA['layout'] ?? null,
				'viewSettings' => $viewA['viewSettings'] ?? null,
			]
		);
		$jsonViewB = json_encode(
			[
				'title' => $viewB['title'],
				'technicalName' => $viewB['technicalName'],
				'description' => $viewB['description'],
				'emoji' => $viewB['emoji'],
				'columns' => $viewB['columns'],
				'columnSettings' => $viewB['columnSettings'],
				'sort' => $viewB['sort'],
				'filter' => $viewB['filter'],
				'layout' => $viewB['layout'] ?? null,
				'viewSettings' => $viewB['viewSettings'] ?? null,
			]
		);

		return $jsonViewA !== $jsonViewB;
	}

	protected function resolveColumnOrderChanges(array $currentSchema, array $updateSchema): void {
		$this->columnOrderChanges = [
			'from' => [],
			'to' => [],
		];
		$currentColumnsMap = $this->getColumnsMapById($currentSchema['columns']);
		$updateColumnsMap = $this->getColumnsMapById($updateSchema['columns']);

		if (empty($currentSchema['columnOrder'])) {
			$order = 1;
			foreach ($currentColumnsMap as $columnId => $column) {
				$this->columnOrderChanges['from'][] = [
					'columnId' => $columnId,
					'columnUuid' => $column['uuid'],
					'columnTitle' => $column['title'],
					'order' => $order++,
				];
			}
		} else {
			foreach ($currentSchema['columnOrder'] as $item) {
				$this->columnOrderChanges['from'][] = [
					'columnId' => $item['columnId'],
					'columnUuid' => $currentColumnsMap[$item['columnId']]['uuid'],
					'columnTitle' => $currentColumnsMap[$item['columnId']]['title'],
					'order' => $item['order'],
				];
			}
		}

		if (empty($updateSchema['columnOrder'])) {
			$order = 1;
			foreach ($updateColumnsMap as $column) {
				$this->columnOrderChanges['to'][] = [
					'columnUuid' => $column['uuid'],
					'columnTitle' => $column['title'],
					'order' => $order++,
				];
			}
		} else {
			foreach ($updateSchema['columnOrder'] as $item) {
				$this->columnOrderChanges['to'][] = [
					'columnUuid' => $updateColumnsMap[$item['columnId']]['uuid'],
					'columnTitle' => $updateColumnsMap[$item['columnId']]['title'],
					'order' => $item['order'],
				];
			}
		}
	}

	protected function resolveSortChanges(array $currentSchema, array $updateSchema): void {
		$this->sortChanges = [
			'from' => [],
			'to' => []
		];
		$currentColumnsMap = $this->getColumnsMapById($currentSchema['columns']);
		$updateColumnsMap = $this->getColumnsMapById($updateSchema['columns']);

		if (empty($currentSchema['sort'])) {
			foreach ($currentColumnsMap as $columnId => $column) {
				$this->sortChanges['from'][] = [
					'columnId' => $columnId,
					'columnUuid' => $column['uuid'],
					'columnTitle' => $column['title'],
					'mode' => 'ASC'
				];
			}
		} else {
			foreach ($currentSchema['sort'] as $item) {
				$this->sortChanges['from'][] = [
					'columnId' => $item['columnId'],
					'columnUuid' => $currentColumnsMap[$item['columnId']]['uuid'],
					'columnTitle' => $currentColumnsMap[$item['columnId']]['title'],
					'mode' => $item['mode'],
				];
			}
		}

		if (empty($updateSchema['sort'])) {
			foreach ($updateColumnsMap as $column) {
				$this->sortChanges['to'][] = [
					'columnUuid' => $column['uuid'],
					'columnTitle' => $column['title'],
					'mode' => 'ASC'
				];
			}
		} else {
			foreach ($updateSchema['sort'] as $item) {
				$this->sortChanges['to'][] = [
					'columnUuid' => $updateColumnsMap[$item['columnId']]['uuid'],
					'columnTitle' => $updateColumnsMap[$item['columnId']]['title'],
					'mode' => $item['mode'],
				];
			}
		}
	}
}
