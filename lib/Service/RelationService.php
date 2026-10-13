<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Dto\LookupSettings;
use OCA\Tables\Dto\RelationSettings;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesColumn from ResponseDefinitions
 * @psalm-import-type TablesPublicColumn from ResponseDefinitions
 * @psalm-import-type TablesRelationData from ResponseDefinitions
 * @psalm-import-type TablesRelationValue from ResponseDefinitions
 */
class RelationService {
	/** @var array<string, array> Cache for formatted relation labels */
	private array $cacheRelationLabels = [];
	private array $cacheRawRelationData = [];

	public function __construct(
		private readonly ColumnMapper $columnMapper,
		private readonly ViewMapper $viewMapper,
		private readonly Row2Mapper $row2Mapper,
		private readonly ColumnService $columnService,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Get all relation data for a table
	 *
	 * @param int $tableId
	 * @return TablesRelationData Relation data grouped by column ID
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getRelationsForTable(int $tableId, ?string $userId = null): array {
		// Check table permissions through ColumnService
		$columns = $this->columnService->findAllByTable($tableId, $userId);

		return $this->getRelationsForColumnList($columns);
	}

	/**
	 * Get all relation data for a view
	 *
	 * @param int $viewId
	 * @return TablesRelationData Relation data grouped by column ID
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getRelationsForView(int $viewId, ?string $userId = null): array {
		// Check view permissions through ColumnService
		$columns = $this->columnService->findAllByView($viewId, $userId);

		return $this->getRelationsForColumnList($columns);
	}

	/**
	 * Get relation labels for a specific column
	 *
	 * @param Column $column
	 * @return array<int, array{id: int, value: string}> Indexed per row id
	 */
	public function getRelationLabels(Column $column): array {
		if ($column->getType() !== Column::TYPE_RELATION) {
			return [];
		}

		try {
			return $this->fetchRelationLabelsForTarget($column);
		} catch (\InvalidArgumentException $e) {
			$this->logger->warning('Invalid relation settings for column ' . $column->getId() . ': ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get relation data for a list of columns
	 *
	 * @param Column[] $columns
	 * @return TablesRelationData Relation data grouped by column ID
	 * @throws InternalError
	 */
	private function getRelationsForColumnList(array $columns): array {
		$columnsMap = $this->fetchColumnsByIds($this->collectRelationColumnIds($columns));
		foreach ($columns as $column) {
			$columnsMap[$column->getId()] = $column;
		}
		$relationColumns = array_filter($columns, fn (Column $column) => $column->getType() === Column::TYPE_RELATION);
		$lookupColumns = array_filter($columns, fn (Column $column) => $column->getType() === Column::TYPE_LOOKUP);
		$this->preloadRelationValues($relationColumns, $lookupColumns, $columnsMap);
		$relationLabels = $this->fetchRelationLabelsForColumns($relationColumns);
		$lookupData = $this->fetchLookupColumns($lookupColumns, $columnsMap);

		return array_map(
			fn (array $data) => ['column' => null, 'values' => $data],
			$relationLabels
		) + $lookupData;
	}

	/**
	 * Fetch relation labels for relation columns
	 *
	 * @param Column[] $columns
	 * @return array<int, array<int, array{id: int, value: string}>> Relation labels, indexed per column ID and row ID
	 * @throws InternalError
	 */
	private function fetchRelationLabelsForColumns(array $columns): array {
		$result = [];

		foreach ($columns as $column) {
			try {
				$result[$column->getId()] = $this->fetchRelationLabelsForTarget($column);
			} catch (\InvalidArgumentException $e) {
				$this->logger->warning('Invalid relation settings for column ' . $column->getId() . ': ' . $e->getMessage());
			}
		}

		return $result;
	}

	/**
	 * Fetch relation data for lookup columns
	 *
	 * @param Column[] $columns
	 * @param array<int, Column> $columnsMap
	 * @return TablesRelationData Relation data grouped by column ID
	 * @throws InternalError
	 */
	private function fetchLookupColumns(array $columns, array $columnsMap): array {
		$result = [];

		// Reuse the batch-fetched columns to avoid duplicate queries
		foreach ($columns as $column) {
			$result[$column->getId()] = ['column' => null, 'values' => []];
			try {
				[$settings, $targetColumn] = $this->resolveLookupTarget($column, $columnsMap);
				$lookupSettings = $column->getCustomSettingsObject(LookupSettings::class);
				$result[$column->getId()] = [
					'column' => $targetColumn->jsonSerialize(),
					'values' => $this->fetchRawRelationValues($settings, $lookupSettings->targetColumnId),
				];
			} catch (\InvalidArgumentException|DoesNotExistException $e) {
				$this->logger->warning('Invalid lookup settings for column ' . $column->getId() . ': ' . $e->getMessage());
			}
		}

		return $result;
	}

	private function getCurrentUserId(): string {
		return $this->userSession->getUser()?->getUID() ?? '';
	}

	/**
	 * @param Column[] $relationColumns
	 * @param Column[] $lookupColumns
	 * @param array<int, Column> $columnsMap
	 */
	private function preloadRelationValues(array $relationColumns, array $lookupColumns, array $columnsMap): void {
		$targets = [];
		foreach ([...$relationColumns, ...$lookupColumns] as $column) {
			try {
				if ($column->isVirtual()) {
					[$settings, $targetColumn] = $this->resolveLookupTarget($column, $columnsMap);
				} else {
					$settings = $column->getCustomSettingsObject(RelationSettings::class);
					$targetColumn = $columnsMap[$settings->labelColumn] ?? null;
					if (!$targetColumn) {
						continue;
					}
					$this->columnService->assertRelationTargetColumn($settings, $targetColumn);
				}
				$targetKey = $settings->relationType . '_' . $settings->targetId;
				$targets[$targetKey]['settings'] = $settings;
				$targets[$targetKey]['columnIds'][$targetColumn->getId()] = true;
			} catch (\InvalidArgumentException|DoesNotExistException $e) {
				$this->logger->warning('Invalid relation settings for column ' . $column->getId(), ['exception' => $e]);
			}
		}

		foreach ($targets as $target) {
			$settings = $target['settings'];
			$cacheKeys = [];
			foreach (array_keys($target['columnIds']) as $columnId) {
				$key = $this->buildRelationCacheKey($settings->relationType, $settings->targetId, $columnId);
				if (!isset($this->cacheRawRelationData[$key])) {
					$cacheKeys[$columnId] = $key;
				}
			}
			if (!$cacheKeys) {
				continue;
			}
			try {
				$rows = $this->fetchRowsForTarget($settings, array_keys($cacheKeys));
			} catch (DoesNotExistException) {
				$rows = [];
			}
			foreach ($cacheKeys as $key) {
				$this->cacheRawRelationData[$key] = [];
			}
			foreach ($rows as $row) {
				$rowId = (int)$row->getId();
				$values = array_column($row->getData(), 'value', 'columnId');
				foreach ($cacheKeys as $columnId => $key) {
					$this->cacheRawRelationData[$key][$rowId] = ['id' => $rowId, 'value' => $values[$columnId] ?? null];
				}
			}
		}
	}

	/**
	 * @param array<int, Column> $columnsMap
	 * @return array{RelationSettings, Column}
	 */
	private function resolveLookupTarget(Column $column, array $columnsMap): array {
		$settings = $column->getCustomSettingsObject(LookupSettings::class);
		$relation = $columnsMap[$settings->relationColumnId] ?? null;
		$target = $columnsMap[$settings->targetColumnId] ?? null;
		if (!$relation || $relation->getType() !== Column::TYPE_RELATION || $relation->getTableId() !== $column->getTableId()) {
			throw new \InvalidArgumentException('Lookup relation must belong to the same table.');
		}
		if (!$target || in_array($target->getType(), [Column::TYPE_RELATION, Column::TYPE_LOOKUP], true)) {
			throw new \InvalidArgumentException('Lookup target is missing or unsupported.');
		}
		$relationSettings = $relation->getCustomSettingsObject(RelationSettings::class);
		$this->columnService->assertRelationTargetColumn($relationSettings, $target);
		return [$relationSettings, $target];
	}

	/**
	 * Fetch relation labels for a specific target
	 *
	 * @param Column $column
	 * @return array<int, array{id: int, value: string}> Indexed per row id
	 * @throws InternalError
	 */
	private function fetchRelationLabelsForTarget(Column $column): array {
		$settings = $column->getCustomSettingsObject(RelationSettings::class);

		$cacheKey = $this->buildRelationCacheKey($settings->relationType, $settings->targetId, $settings->labelColumn);
		if (isset($this->cacheRelationLabels[$cacheKey])) {
			return $this->cacheRelationLabels[$cacheKey];
		}

		try {
			$targetColumn = $this->columnMapper->find($settings->labelColumn);
			$this->columnService->assertRelationTargetColumn($settings, $targetColumn);
			$rows = $this->fetchRawRelationValues($settings, $settings->labelColumn);
		} catch (DoesNotExistException) {
			$this->cacheRelationLabels[$cacheKey] = [];
			return [];
		}

		$result = $this->buildRelationLabels($rows, $targetColumn);
		$this->cacheRelationLabels[$cacheKey] = $result;

		return $result;
	}

	/**
	 * Build a cache key for relation data
	 */
	private function buildRelationCacheKey(string $relationType, int $targetId, int $labelColumn): string {
		return sprintf('%s_%d_%d_%s', $relationType, $targetId, $labelColumn, $this->getCurrentUserId());
	}

	/**
	 * Collect all column IDs needed for relation and lookup processing
	 *
	 * @param Column[] $columns
	 * @return int[]
	 */
	private function collectRelationColumnIds(array $columns): array {
		$columnIds = [];

		foreach ($columns as $column) {
			try {
				if ($column->getType() === Column::TYPE_LOOKUP) {
					$settings = $column->getCustomSettingsObject(LookupSettings::class);
					$columnIds[] = $settings->targetColumnId;
					$columnIds[] = $settings->relationColumnId;
				} elseif ($column->getType() === Column::TYPE_RELATION) {
					$columnIds[] = $column->getCustomSettingsObject(RelationSettings::class)->labelColumn;
				}
			} catch (\InvalidArgumentException $e) {
				$this->logger->warning('Invalid relation settings for column ' . $column->getId(), ['exception' => $e]);
			}
		}

		return array_values(array_unique($columnIds));
	}

	/**
	 * Fetch multiple columns by their IDs in a single batch
	 *
	 * @param int[] $columnIds
	 * @return array<int, Column>
	 */
	private function fetchColumnsByIds(array $columnIds): array {
		if (empty($columnIds)) {
			return [];
		}

		try {
			$columns = $this->columnMapper->findAll($columnIds);
			$result = [];
			foreach ($columns as $column) {
				$result[$column->getId()] = $column;
			}
			return $result;
		} catch (\Exception $e) {
			$this->logger->warning('Failed to fetch columns: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Fetch raw values for a relation target column
	 *
	 * @param RelationSettings $settings
	 * @param int $targetColumnId
	 * @return array<int, array{id: int, value: TablesRelationValue}>
	 */
	private function fetchRawRelationValues(RelationSettings $settings, int $targetColumnId): array {
		$cacheKey = $this->buildRelationCacheKey($settings->relationType, $settings->targetId, $targetColumnId);
		if (isset($this->cacheRawRelationData[$cacheKey])) {
			return $this->cacheRawRelationData[$cacheKey];
		}

		try {
			// Fetch only the requested column; labels do not determine whether a row is referenced
			$rows = $this->fetchRowsForTarget($settings, [$targetColumnId]);
			$lookupData = $this->buildRawRelationValues($rows, $targetColumnId);

			$this->cacheRawRelationData[$cacheKey] = $lookupData;
			return $lookupData;
		} catch (DoesNotExistException $e) {
			$this->logger->warning('Failed to fetch lookup data: ' . $e->getMessage());
			$this->cacheRawRelationData[$cacheKey] = [];
			return [];
		}
	}

	/**
	 * Fetch rows for a relation target
	 *
	 * @param RelationSettings $settings
	 * @param int[] $columnIds
	 * @return Row2[]
	 * @throws DoesNotExistException
	 */
	private function fetchRowsForTarget(RelationSettings $settings, array $columnIds): array {
		if ($settings->isView()) {
			$view = $this->viewMapper->find($settings->targetId);
			return $this->row2Mapper->findAll(
				$columnIds,
				$view->getTableId(),
				filter: $view->getFilterArray(),
				sort: $view->getSortArray(),
				userId: $this->getCurrentUserId()
			);
		}

		return $this->row2Mapper->findAll(
			$columnIds,
			$settings->targetId,
			userId: $this->getCurrentUserId()
		);
	}

	/**
	 * Build relation labels from raw values
	 *
	 * @param array<int, array{id: int, value: TablesRelationValue}> $rows
	 * @param Column $column
	 * @return array<int, array{id: int, value: string}>
	 */
	private function buildRelationLabels(array $rows, Column $column): array {
		$result = [];

		foreach ($rows as $rowId => $row) {
			$result[$rowId] = [
				'id' => $rowId,
				'value' => $this->formatRelationLabel($column, $row['value']),
			];
		}

		return $result;
	}

	/**
	 * Build lookup values from rows containing the target column
	 *
	 * @param Row2[] $rows
	 * @param int $targetColumnId
	 * @return array<int, array{id: int, value: TablesRelationValue}>
	 */
	private function buildRawRelationValues(array $rows, int $targetColumnId): array {
		$result = [];

		foreach ($rows as $row) {
			$data = $row->getData();
			$rowId = (int)$row->getId();

			// Index the row's values by column ID
			$values = array_column($data, 'value', 'columnId');

			// Find the target column value
			$targetValue = $values[$targetColumnId] ?? null;

			// A relation references the row ID independently of its label
			$result[$rowId] = [
				'id' => $rowId,
				'value' => $targetValue,
			];
		}

		return $result;
	}

	/**
	 * Format a cell value for display as a relation label.
	 * Relation labels only support text-line and number columns.
	 */
	private function formatRelationLabel(Column $column, mixed $value): string {
		if ($value === null || $value === '' || $value === []) {
			return '';
		}

		return match ($column->getType()) {
			Column::TYPE_TEXT => trim(strip_tags((string)$value)),
			Column::TYPE_NUMBER => $this->formatNumberLabel($column, $value),
			default => (string)$value,
		};
	}

	private function formatNumberLabel(Column $column, mixed $value): string {
		if ($value === null || $value === '') {
			return '';
		}
		if (!is_numeric($value)) {
			return (string)$value;
		}
		$decimals = $column->getNumberDecimals() ?? 0;
		$formatted = number_format((float)$value, $decimals, '.', '');
		return $column->getNumberPrefix() . $formatted . $column->getNumberSuffix();
	}
}
