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
	/** @var array<string, array> Cache for relation data */
	private array $cacheRelationData = [];

	public function __construct(
		private ColumnMapper $columnMapper,
		private ViewMapper $viewMapper,
		private Row2Mapper $row2Mapper,
		private ColumnService $columnService,
		private ?string $userId,
		private LoggerInterface $logger,
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

		$relationLookupColumns = array_filter($columns, function ($column) {
			return $column->getType() === Column::TYPE_RELATION_LOOKUP;
		});

		$relationData = $this->getRelationsForColumns($relationColumns);
		$relationLookupData = $this->getRelationsForLookupColumns($relationLookupColumns, $relationData);

		return $relationData + $relationLookupData;
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

		$relationLookupColumns = array_filter($columns, function ($column) {
			return $column->getType() === Column::TYPE_RELATION_LOOKUP;
		});

		$relationData = $this->getRelationsForColumns($relationColumns);
		$relationLookupData = $this->getRelationsForLookupColumns($relationLookupColumns, $relationData);

		return $relationData + $relationLookupData;
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

			// Assign the same data to all columns with this target
			foreach ($columns as $column) {
				$result[$column->getId()] = $relationData;
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

		$target = sprintf('%s_%s_%s', $settings['relationType'], $settings['targetId'], $settings['labelColumn']);

		return $this->getRelationDataForTarget($target, $column);
	}

	/**
	 * Get relation data for relation lookup columns
	 *
	 * @param Column[] $relationLookupColumns
	 * @param array $existingRelationData Existing relation data from relation columns
	 * @return array Relation data grouped by column ID
	 * @throws InternalError
	 */
	private function getRelationsForLookupColumns(array $relationLookupColumns, array $existingRelationData): array {
		$result = [];

		foreach ($relationLookupColumns as $lookupColumn) {
			$settings = $lookupColumn->getCustomSettingsArray();
			$relationColumnId = $settings['relationColumnId'] ?? null;

			if (!$relationColumnId) {
				continue;
			}

			// Get the relation data from the parent relation column
			$relationData = $existingRelationData[$relationColumnId] ?? null;

			// Always fetch the relation column object to get its settings
			$relationColumn = null;
			try {
				$relationColumn = $this->columnMapper->find($relationColumnId);
			} catch (DoesNotExistException $e) {
				$this->logger->warning('Relation column not found for relation lookup: ' . $relationColumnId);
				continue;
			}

			if (!$relationData) {
				// If the relation column data doesn't exist, try to fetch it
				if ($relationColumn->getType() === Column::TYPE_RELATION) {
					$relationData = $this->getRelationData($relationColumn);
				}
			}

			if (!$relationData) {
				continue;
			}

			// Get the target column metadata for the lookup
			$targetColumnId = $settings['targetColumnId'] ?? null;
			$targetColumn = null;
			if ($targetColumnId) {
				try {
					$targetColumn = $this->columnMapper->find($targetColumnId);
				} catch (DoesNotExistException $e) {
					$this->logger->warning('Target column not found for relation lookup: ' . $targetColumnId);
				}
			}

			// Fetch relation data using the target column instead of the parent relation's label column
			$lookupData = [];
			if ($targetColumn && $relationColumn) {
				$relationSettings = $relationColumn->getCustomSettingsArray();
				$isView = ($relationSettings['relationType'] ?? '') === 'view';
				$targetId = $relationSettings['targetId'] ?? null;

				$cacheKey = sprintf('%s_%s_%s_%s', $relationSettings['relationType'] ?? '', $targetId, $targetColumnId, $this->userId ?? 'anonymous');
				if (isset($this->cacheRelationData[$cacheKey])) {
					$lookupData = $this->cacheRelationData[$cacheKey];
				} else {
					try {
						if ($isView && $targetId) {
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
						} elseif ($targetId) {
							$rows = $this->row2Mapper->findAll(
								[$targetColumn->getId()],
								$targetId,
								null,
								null,
								null,
								null,
								$this->userId
							);
						} else {
							$rows = [];
						}

						// Build a map of target row IDs to their values
						$targetRowValues = [];
						foreach ($rows as $row) {
							$data = $row->getData();
							$displayFieldData = array_filter($data, function ($item) use ($targetColumnId) {
								return $item['columnId'] === (int)$targetColumnId;
							});
							$value = reset($displayFieldData)['value'] ?? null;

							// Convert value based on column type
							if ($targetColumn->getType() === Column::TYPE_NUMBER) {
								$targetRowValues[(int)$row->getId()] = is_numeric($value) ? (float)$value : null;
							} else {
								$targetRowValues[(int)$row->getId()] = (string)$value;
							}
						}

						// Map the relation data (which has current table row IDs) to target table row IDs
						// The relation data structure is: {currentRowId: {id: targetRowId, label: targetLabel}}
						foreach ($relationData as $currentRowId => $relationInfo) {
							$targetRowId = $relationInfo['id'] ?? null;
							if ($targetRowId !== null && isset($targetRowValues[$targetRowId])) {
								$lookupData[$currentRowId] = [
									'id' => $currentRowId,
									'label' => $targetRowValues[$targetRowId],
								];
							}
						}

						$this->cacheRelationData[$cacheKey] = $lookupData;
					} catch (DoesNotExistException $e) {
						$this->logger->warning('Failed to fetch lookup data: ' . $e->getMessage());
					}
				}
			}

			// Structure the data for the frontend: {column: Column, data: {rowId: {id, label}}}
			$result[$lookupColumn->getId()] = [
				'column' => $targetColumn ? $targetColumn->jsonSerialize() : null,
				'data' => $lookupData,
			];
		}

		return $result;
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

		$isView = $settings[Column::RELATION_TYPE] === 'view';
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
