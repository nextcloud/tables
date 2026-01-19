<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

class RelationService {

	private ColumnMapper $columnMapper;
	private ViewMapper $viewMapper;
	private Row2Mapper $row2Mapper;
	private ColumnService $columnService;
	private LoggerInterface $logger;
	private ?string $userId;

	/** @var array<string, array> Cache for relation data */
	private array $cacheRelationData = [];

	public function __construct(
		ColumnMapper $columnMapper,
		ViewMapper $viewMapper,
		Row2Mapper $row2Mapper,
		ColumnService $columnService,
		private PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
	) {
		$this->columnMapper = $columnMapper;
		$this->viewMapper = $viewMapper;
		$this->row2Mapper = $row2Mapper;
		$this->columnService = $columnService;
		$this->logger = $logger;
		$this->userId = $userId;
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
		// Check if the current user has read access to the table
		if (!$this->permissionsService->canReadColumnsByTableId($tableId, $this->userId)) {
			throw new PermissionError('User does not have read access to this table');
		}

		$columns = $this->columnService->findAllByTable($tableId);

		return $this->getRelationsForColumns($columns);
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
		// Check if the current user has read access to the view
		if (!$this->permissionsService->canReadColumnsByViewId($viewId, $this->userId)) {
			throw new PermissionError('User does not have read access to this view');
		}

		$columns = $this->columnService->findAllByView($viewId);

		return $this->getRelationsForColumns($columns);
	}

	/**
	 * Get relation data for specific columns
	 *
	 * @param Column[] $relationColumns
	 * @return array Relation data grouped by column ID
	 * @throws InternalError
	 */
	private function getRelationsForColumns(array $columns): array {
		$relationColumns = array_filter($columns, function ($column) {
			return $column->getType() === Column::TYPE_RELATION;
		});
		$relationLookupColumns = array_filter($columns, function ($column) {
			return $column->getType() === Column::TYPE_RELATION_LOOKUP;
		});

		$relationColumnsSettings = [];
		foreach ($relationColumns as $column) {
			$relationColumnsSettings[$column->getId()] = $column->getCustomSettingsArray();
		}
		$groupedColumns = $this->groupColumnsRelationByTarget($relationColumns);
		$groupedColumns = $this->groupColumnsRelationLookupByTarget($groupedColumns, $relationColumnsSettings, $relationLookupColumns);

		$result = [];
		foreach ($groupedColumns as $target => $data) {
			$relationData = $this->getRelationDataForTarget(
				$data['relationType'],
				$data['targetId'],
				array_keys($data['columns'])
			);
			// Assign data to each column based on its displayField
			foreach ($relationData as $targetColumnId => $columnData) {
				foreach ($data['columns'][$targetColumnId] as $columnId) {
					$result[$columnId] = $columnData;
				}
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
	private function groupColumnsRelationByTarget(array $columns): array {
		$groups = [];

		foreach ($columns as $column) {
			$settings = $column->getCustomSettingsArray();
			if (empty($settings['relationType']) || empty($settings['targetId']) || empty($settings['labelColumn'])) {
				continue;
			}

			$target = sprintf('%s_%s', $settings['relationType'], $settings['targetId']);
			if (!isset($groups[$target])) {
				$groups[$target] = ['relationType' => $settings['relationType'], 'targetId' => (int)$settings['targetId'], 'columns' => []];
			}

			if (!isset($groups[$target]['columns'][$settings['displayField']])) {
				$groups[$target]['columns'][$settings['displayField']] = [];
			}

			$groups[$target]['columns'][$settings['displayField']][] = $column->getId();
		}

		return $groups;
	}

	private function groupColumnsRelationLookupByTarget(array $groupedColumns, array $relationColumnsSettings, array $relationLookupColumns): array {
		foreach ($relationLookupColumns as $column) {
			$relationLookupSettings = $column->getCustomSettingsArray();
			$relationColumnId = $relationLookupSettings['relationColumnId'];
			if (!isset($relationColumnsSettings[$relationColumnId])) {
				try {
					$relationColumn = $this->columnMapper->find($relationColumnId);
				} catch (DoesNotExistException $e) {
					continue;
				}

				$relationColumnsSettings[$relationColumnId] = $relationColumn->getCustomSettingsArray();
			}

			$settings = $relationColumnsSettings[$relationColumnId];

			$target = sprintf('%s_%s', $settings['relationType'], $settings['targetId']);

			if (!isset($groupedColumns[$target])) {
				$groupedColumns[$target] = ['relationType' => $settings['relationType'], 'targetId' => (int)$settings['targetId'], 'columns' => []];
			}

			if (!isset($groupedColumns[$target]['columns'][$relationLookupSettings['targetColumnId']])) {
				$groupedColumns[$target]['columns'][$relationLookupSettings['targetColumnId']] = [];
			}

			$groupedColumns[$target]['columns'][$relationLookupSettings['targetColumnId']][] = $column->getId();
		}

		return $groupedColumns;
	}

	/**
	 * Get relation data for a specific column
	 *
	 * @param Column $column
	 * @return array
	 */
	public function getRelationData(Column $column): array {
		if ($column->getType() !== Column::TYPE_RELATION) {
			return [];
		}

		$settings = $column->getCustomSettingsArray();
		if (empty($settings['relationType']) || empty($settings['targetId']) || empty($settings['labelColumn'])) {
			return [];
		}

		$relationData = $this->getRelationDataForTarget(
			$settings['relationType'],
			(int)$settings['targetId'],
			[(int)$settings['displayField']]
		);

		return $relationData[(int)$settings['displayField']]['data'] ?? [];
	}

	/**
	 * Create a meta-column object for the given meta ID and table ID
	 */
	private function createMetaColumn(int $metaId, int $tableId): Column {
		$column = new Column();
		$column->setTableId($tableId);
		$column->setTitle('Meta Column');
		$column->setType($this->getMetaColumnType($metaId));
		$column->setSubtype($this->getMetaColumnSubtype($metaId));
		$column->setId($metaId);
		return $column;
	}

	/**
	 * Get the type for a meta-column
	 */
	private function getMetaColumnType(int $metaId): string {
		return match ($metaId) {
			Column::TYPE_META_ID => 'number',
			Column::TYPE_META_CREATED_BY => 'text',
			Column::TYPE_META_CREATED_AT => 'datetime',
			Column::TYPE_META_UPDATED_BY => 'text',
			Column::TYPE_META_UPDATED_AT => 'datetime',
			default => 'text-line'
		};
	}

	/**
	 * Get the subtype for a meta-column
	 */
	private function getMetaColumnSubtype(int $metaId): string {
		return match ($metaId) {
			Column::TYPE_META_ID => '',
			Column::TYPE_META_CREATED_BY => 'line',
			Column::TYPE_META_CREATED_AT => '',
			Column::TYPE_META_UPDATED_BY => 'line',
			Column::TYPE_META_UPDATED_AT => '',
			default => ''
		};
	}

	/**
	 * Get relation data for the target table/view with support for meta columns
	 *
	 * @param string $target
	 * @param Column $column
	 * @return array
	 * @throws InternalError
	 */
	private function getRelationDataForTarget(string $relationType, int $targetId, array $columnIds): array {
		$isView = $relationType === 'view';
		$result = [];

		$columnsToFetch = [];
		$metaColumnsToFetch = [];
		$normalColumnsToFetch = [];

		// Check the cache for each column and collect columns that need to be fetched
		foreach ($columnIds as $columnId) {
			$cacheKey = sprintf('%s_%d_%d_%s', $relationType, $targetId, $columnId, $this->userId ?? 'anonymous');
			if (isset($this->cacheRelationData[$cacheKey])) {
				$result[$columnId] = $this->cacheRelationData[$cacheKey];
				continue;
			}
			$columnsToFetch[] = $columnId;
			if (\OCA\Tables\Db\Column::isValidMetaTypeId((int)$columnId)) {
				$metaColumnsToFetch[] = (int)$columnId;
			} else {
				$normalColumnsToFetch[] = (int)$columnId;
			}
		}

		// If all columns are cached, return immediately
		if (empty($columnsToFetch)) {
			return $result;
		}

		// Prepare Column entities for normal columns
		$targetColumns = [];
		$targetColumnsById = [];
		foreach ($normalColumnsToFetch as $columnId) {
			try {
				$columnEntity = $this->columnMapper->find($columnId);
				$targetColumns[] = $columnEntity;
				$targetColumnsById[$columnId] = $columnEntity;
			} catch (DoesNotExistException $e) {
				// Cache empty result for non-existent column
				$cacheKey = sprintf('%s_%d_%d_%s', $relationType, $targetId, $columnId, $this->userId ?? 'anonymous');
				$this->cacheRelationData[$cacheKey] = ['data' => [], 'column' => null];
				$result[$columnId] = $this->cacheRelationData[$cacheKey];
			}
		}

		// If there are no normal columns but meta columns exist, add a fallback column
		if (empty($targetColumns) && !empty($metaColumnsToFetch)) {
			try {
				if ($isView) {
					$view = $this->viewMapper->find($targetId);
					$available = $this->columnService->findAllByTable($view->getTableId());
				} else {
					$available = $this->columnService->findAllByTable($targetId);
				}
				foreach ($available as $col) {
					if ($col->getType() !== \OCA\Tables\Db\Column::TYPE_RELATION_LOOKUP) {
						$targetColumns[] = $col;
						break;
					}
				}
			} catch (DoesNotExistException $e) {
				// ignore; no fallback
			}
		}

		// Fetch rows for the target
		$rows = [];
		if (!empty($targetColumns)) {
			try {
				$targetColumnIds = array_map(fn ($column) => $column->getId(), $targetColumns);
				if ($isView) {
					$view = $this->viewMapper->find($targetId);
					$rows = $this->row2Mapper->findAll(
						$targetColumnIds,
						$view->getTableId(),
						null,
						null,
						$view->getFilterArray(),
						$view->getSortArray(),
						$this->userId
					);
				} else {
					$rows = $this->row2Mapper->findAll(
						$targetColumnIds,
						$targetId,
						null,
						null,
						null,
						null,
						$this->userId
					);
				}
			} catch (DoesNotExistException $e) {
				// Cache empty results for all columns that were being fetched
				foreach ($columnsToFetch as $columnId) {
					$cacheKey = sprintf('%s_%d_%d_%s', $relationType, $targetId, $columnId, $this->userId ?? 'anonymous');
					$this->cacheRelationData[$cacheKey] = ['data' => [], 'column' => null];
					$result[$columnId] = $this->cacheRelationData[$cacheKey];
				}
				return $result;
			}
		} else {
			// No columns available to fetch rows; return empty for requested columns
			foreach ($columnsToFetch as $columnId) {
				$cacheKey = sprintf('%s_%d_%d_%s', $relationType, $targetId, $columnId, $this->userId ?? 'anonymous');
				$this->cacheRelationData[$cacheKey] = ['data' => [], 'column' => null];
				$result[$columnId] = $this->cacheRelationData[$cacheKey];
			}
			return $result;
		}

		// Determine table id for meta column objects
		$targetTableId = null;
		if ($isView) {
			try {
				$view = $this->viewMapper->find($targetId);
				$targetTableId = $view->getTableId();
			} catch (DoesNotExistException $e) {
				$targetTableId = null;
			}
		} else {
			$targetTableId = $targetId;
		}

		// Process rows and cache data for each normal column
		foreach ($normalColumnsToFetch as $columnId) {
			$columnData = [];
			foreach ($rows as $row) {
				$data = $row->getData();
				$columnFieldData = array_filter($data, function ($item) use ($columnId) {
					return $item['columnId'] === $columnId;
				});
				$value = reset($columnFieldData)['value'] ?? null;

				$columnData[$row->getId()] = [
					'id' => $row->getId(),
					'label' => $value,
				];
			}

			$cacheKey = sprintf('%s_%d_%d_%s', $relationType, $targetId, $columnId, $this->userId ?? 'anonymous');
			// If the target column is of type relation, expose it as selection with populated options
			$columnForCache = null;
			$baseTargetColumn = $targetColumnsById[$columnId] ?? null;
			if ($baseTargetColumn !== null && $baseTargetColumn->getType() === Column::TYPE_RELATION) {
				$settings = $baseTargetColumn->getCustomSettingsArray();
				$selectionOptions = [];
				if (!empty($settings['relationType']) && !empty($settings['targetId']) && !empty($settings['displayField'])) {
					// Build options from the relation's own display values
					$nestedRelationData = $this->getRelationDataForTarget(
						$settings['relationType'],
						(int)$settings['targetId'],
						[(int)$settings['displayField']]
					);
					$optionsData = $nestedRelationData[(int)$settings['displayField']]['data'] ?? [];
					foreach ($optionsData as $opt) {
						$selectionOptions[] = [
							'id' => $opt['id'],
							'label' => (string)($opt['label'] ?? ''),
						];
					}
				}

				$selectionColumn = new Column();
				$selectionColumn->setTableId($baseTargetColumn->getTableId());
				$selectionColumn->setTitle($baseTargetColumn->getTitle());
				$selectionColumn->setType(Column::TYPE_SELECTION);
				$selectionColumn->setSubtype('');
				$selectionColumn->setSelectionOptionsArray($selectionOptions);
				$columnForCache = $selectionColumn;
			} else {
				// Fallback: original column
				try {
					$columnForCache = $this->columnMapper->find($columnId);
				} catch (DoesNotExistException $e) {
					$columnForCache = null;
				}
			}

			$this->cacheRelationData[$cacheKey] = ['data' => $columnData, 'column' => $columnForCache];
			$result[$columnId] = $this->cacheRelationData[$cacheKey];
		}

		// Process rows and cache data for each meta column
		foreach ($metaColumnsToFetch as $metaId) {
			$columnData = [];
			foreach ($rows as $row) {
				$label = null;
				switch ($metaId) {
					case \OCA\Tables\Db\Column::TYPE_META_ID:
						$label = $row->getId();
						break;
					case \OCA\Tables\Db\Column::TYPE_META_CREATED_BY:
						$label = $row->getCreatedBy();
						break;
					case \OCA\Tables\Db\Column::TYPE_META_CREATED_AT:
						$label = $row->getCreatedAt();
						break;
					case \OCA\Tables\Db\Column::TYPE_META_UPDATED_BY:
						$label = $row->getLastEditBy();
						break;
					case \OCA\Tables\Db\Column::TYPE_META_UPDATED_AT:
						$label = $row->getLastEditAt();
						break;
				}
				$columnData[$row->getId()] = [
					'id' => $row->getId(),
					'label' => $label,
				];
			}

			$cacheKey = sprintf('%s_%d_%d_%s', $relationType, $targetId, $metaId, $this->userId ?? 'anonymous');
			$columnObj = $targetTableId !== null ? $this->createMetaColumn($metaId, $targetTableId) : null;
			$this->cacheRelationData[$cacheKey] = ['data' => $columnData, 'column' => $columnObj];
			$result[$metaId] = $this->cacheRelationData[$cacheKey];
		}

		return $result;
	}
}
