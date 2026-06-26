<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;

class RelationService {
	/** @var array<string, array> Cache for relation data */
	private array $cacheRelationData = [];

	/** @var array<string, bool> Cache for manager accessibility decisions, keyed by host table + target */
	private array $cacheManagerAccess = [];

	public function __construct(
		private ColumnMapper $columnMapper,
		private ViewMapper $viewMapper,
		private TableMapper $tableMapper,
		private Row2Mapper $row2Mapper,
		private ColumnService $columnService,
		private PermissionsService $permissionsService,
		private ShareService $shareService,
		private ?string $userId,
	) {
	}

	/**
	 * Get all relation data for a table
	 *
	 * @param int $tableId
	 * @return array Relation data grouped by column ID
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getRelationsForTable(int $tableId): array {
		// Check table permissions through ColumnService
		$columns = $this->columnService->findAllByTable($tableId);

		$relationColumns = array_filter($columns, function ($column) {
			return $column->getType() === Column::TYPE_RELATION;
		});

		return $this->getRelationsForColumns($relationColumns);
	}

	/**
	 * Get all relation data for a view
	 *
	 * @param int $viewId
	 * @return array Relation data grouped by column ID
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getRelationsForView(int $viewId): array {
		// Check view permissions through ColumnService
		$columns = $this->columnService->findAllByView($viewId);

		$relationColumns = array_filter($columns, function ($column) {
			return $column->getType() === Column::TYPE_RELATION;
		});

		return $this->getRelationsForColumns($relationColumns);
	}

	/**
	 * Get relation data for specific columns
	 *
	 * @param Column[] $relationColumns
	 * @return array Relation data grouped by column ID
	 * @throws InternalError
	 */
	private function getRelationsForColumns(array $relationColumns): array {
		// Group columns by their target (relationType + targetId + labelColumn)
		$result = [];
		$groupedColumns = $this->groupColumnsByTarget($relationColumns);
		foreach ($groupedColumns as $target => $columns) {
			$relationData = $this->getRelationDataForTarget($target, $columns[0]);

			// Assign the same data to all columns with this target, but only when
			// the relation is still backed by a manager of the hosting table.
			foreach ($columns as $column) {
				$result[$column->getId()] = $this->isTargetAccessibleByManager($column) ? $relationData : [];
			}
		}

		return $result;
	}

	/**
	 * Group relation columns by their target configuration
	 *
	 * @param Column[] $columns
	 * @return array
	 */
	private function groupColumnsByTarget(array $columns): array {
		$groups = [];

		foreach ($columns as $column) {
			$settings = $column->getCustomSettingsArray();
			if (empty($settings['relationType']) || empty($settings['targetId']) || empty($settings['labelColumn'])) {
				continue;
			}

			$target = sprintf('%s_%s_%s', $settings['relationType'], $settings['targetId'], $settings['labelColumn']);
			if (!isset($groups[$target])) {
				$groups[$target] = [];
			}
			$groups[$target][] = $column;
		}

		return $groups;
	}

	/**
	 * Get relation data for a specific column
	 *
	 * @param Column $column
	 * @return array<int, array{id: int, label: string}> Indexed per row id
	 */
	public function getRelationData(Column $column): array {
		if ($column->getType() !== Column::TYPE_RELATION) {
			return [];
		}

		$settings = $column->getCustomSettingsArray();
		if (empty($settings['relationType']) || empty($settings['targetId']) || empty($settings['labelColumn'])) {
			return [];
		}

		if (!$this->isTargetAccessibleByManager($column)) {
			return [];
		}

		$target = sprintf('%s_%s_%s', $settings['relationType'], $settings['targetId'], $settings['labelColumn']);

		return $this->getRelationDataForTarget($target, $column);
	}

	public function isTargetAccessibleByManager(Column $relationColumn): bool {
		$settings = $relationColumn->getCustomSettingsArray();
		$relationType = $settings[Column::RELATION_TYPE] ?? null;
		$targetId = isset($settings[Column::RELATION_TARGET_ID]) ? (int)$settings[Column::RELATION_TARGET_ID] : null;
		if (empty($relationType) || empty($targetId)) {
			return false;
		}

		$hostTableId = $relationColumn->getTableId();
		$cacheKey = sprintf('%s_%s_%s', $hostTableId, $relationType, $targetId);
		if (isset($this->cacheManagerAccess[$cacheKey])) {
			return $this->cacheManagerAccess[$cacheKey];
		}

		$candidateUserIds = [];
		try {
			$hostTable = $this->tableMapper->find($hostTableId);
			if ($hostTable->getOwnership() !== null && $hostTable->getOwnership() !== '') {
				$candidateUserIds[] = $hostTable->getOwnership();
			}
		} catch (DoesNotExistException|\OCP\AppFramework\Db\MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			// host table gone, so nothing to expose
			$this->cacheManagerAccess[$cacheKey] = false;
			return false;
		}

		try {
			$candidateUserIds = array_unique(array_merge(
				$candidateUserIds,
				$this->shareService->findManagerUserIds($hostTableId, Application::NODE_TYPE_NAME_TABLE),
			));
		} catch (InternalError $e) {
			// fall back to the owner only
		}

		$accessible = false;
		foreach ($candidateUserIds as $candidateUserId) {
			if ($candidateUserId === null || $candidateUserId === '') {
				continue;
			}
			if ($relationType === Application::NODE_TYPE_NAME_VIEW) {
				$canRead = $this->permissionsService->canReadColumnsByViewId($targetId, $candidateUserId);
			} elseif ($relationType === Application::NODE_TYPE_NAME_TABLE) {
				$canRead = $this->permissionsService->canReadColumnsByTableId($targetId, $candidateUserId);
			} else {
				$canRead = false;
			}
			if ($canRead) {
				$accessible = true;
				break;
			}
		}

		$this->cacheManagerAccess[$cacheKey] = $accessible;
		return $accessible;
	}

	/**
	 * Get relation data for a specific target
	 *
	 * @param string $target
	 * @param Column $column
	 * @return array<int, array{id: int, label: string}> Indexed per row id
	 * @throws InternalError
	 */
	private function getRelationDataForTarget(string $target, Column $column): array {
		// Check cache first
		$cacheKey = $target . '_' . ($this->userId ?? 'anonymous');
		if (isset($this->cacheRelationData[$cacheKey])) {
			return $this->cacheRelationData[$cacheKey];
		}

		$settings = $column->getCustomSettingsArray();
		if (empty($settings[Column::RELATION_TYPE]) || empty($settings[Column::RELATION_TARGET_ID]) || empty($settings[Column::RELATION_LABEL_COLUMN])) {
			$this->cacheRelationData[$cacheKey] = [];
			return [];
		}

		$isView = $settings[Column::RELATION_TYPE] === Application::NODE_TYPE_NAME_VIEW;
		$targetId = $settings[Column::RELATION_TARGET_ID] ?? null;

		try {
			$targetColumn = $this->columnMapper->find($settings[Column::RELATION_LABEL_COLUMN]);
			if ($isView) {
				$view = $this->viewMapper->find($targetId);
				$rows = $this->row2Mapper->findAll(
					[$targetColumn->getId()],
					$view->getTableId(),
					null,
					null,
					$view->getFilterArray(),
					$view->getSortArray(),
					$this->userId
				);
			} else {
				$rows = $this->row2Mapper->findAll(
					[$targetColumn->getId()],
					$targetId,
					null,
					null,
					null,
					null,
					$this->userId
				);
			}
		} catch (DoesNotExistException $e) {
			$this->cacheRelationData[$cacheKey] = [];
			return [];
		}

		$result = [];
		foreach ($rows as $row) {
			$data = $row->getData();
			$displayFieldData = array_filter($data, function ($item) use ($settings) {
				return $item['columnId'] === (int)$settings[Column::RELATION_LABEL_COLUMN];
			});
			$value = reset($displayFieldData)['value'] ?? null;

			// Structure compatible with Row2 format: {id: int, label: string}
			$rowId = (int)$row->getId();
			$result[$rowId] = [
				'id' => $rowId,
				'label' => (string)$value,
			];
		}

		$this->cacheRelationData[$cacheKey] = $result;
		return $result;
	}
}
