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
use OCA\Tables\Dto\RelationLookupSettings;
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
 */
class RelationService {
	/** @var array<string, array> Cache for relation data */
	private array $cacheRelationData = [];

	public function __construct(
		private ColumnMapper $columnMapper,
		private ViewMapper $viewMapper,
		private Row2Mapper $row2Mapper,
		private ColumnService $columnService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get all relation data for a table
	 *
	 * @param int $tableId
	 * @return array<int, array{column: ?TablesColumn, values: array<int, array{id: int, value: mixed}>}> Relation data grouped by column ID
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getRelationsForTable(int $tableId): array {
		// Check table permissions through ColumnService
		$columns = $this->columnService->findAllByTable($tableId);

		return $this->getRelationsForColumnList($columns);
	}

	/**
	 * Get all relation data for a view
	 *
	 * @param int $viewId
	 * @return array<int, array{column: ?TablesColumn, values: array<int, array{id: int, value: mixed}>}> Relation data grouped by column ID
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function getRelationsForView(int $viewId): array {
		// Check view permissions through ColumnService
		$columns = $this->columnService->findAllByView($viewId);

		return $this->getRelationsForColumnList($columns);
	}

	/**
	 * Get relation data for a specific column
	 *
	 * @param Column $column
	 * @return array<int, array{id: int, value: string}> Indexed per row id
	 */
	public function getRelationData(Column $column): array {
		if ($column->getType() !== Column::TYPE_RELATION) {
			return [];
		}

		try {
			return $this->fetchRelationValuesForTarget($column);
		} catch (\InvalidArgumentException $e) {
			$this->logger->warning('Invalid relation settings for column ' . $column->getId() . ': ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Get relation data for a list of columns
	 *
	 * @param Column[] $columns
	 * @return array<int, array{column: ?TablesColumn, values: array<int, array{id: int, value: mixed}>}> Relation data grouped by column ID
	 * @throws InternalError
	 */
	private function getRelationsForColumnList(array $columns): array {
		$relationColumns = array_filter($columns, fn (Column $column) => $column->getType() === Column::TYPE_RELATION);
		$relationData = $this->fetchRelationColumns($relationColumns);

		$lookupColumns = array_filter($columns, fn (Column $column) => $column->getType() === Column::TYPE_RELATION_LOOKUP);
		$relationLookupData = $this->fetchRelationLookupColumns($lookupColumns);

		return array_map(
			fn (array $data) => ['column' => null, 'values' => $data],
			$relationData
		) + $relationLookupData;
	}

	/**
	 * Fetch relation data for relation columns
	 *
	 * @param Column[] $columns
	 * @return array<int, array<int, array{id: int, value: string}>> Relation data, indexed per column ID and row ID
	 * @throws InternalError
	 */
	private function fetchRelationColumns(array $columns): array {
		$result = [];
		$fetchedTargets = [];

		foreach ($columns as $column) {
			try {
				$settings = $column->getCustomSettingsObject(RelationSettings::class);
				$targetKey = $this->buildRelationCacheKey($settings->relationType, $settings->targetId, $settings->labelColumn);

				if (!isset($fetchedTargets[$targetKey])) {
					$fetchedTargets[$targetKey] = $this->fetchRelationValuesForTarget($column);
				}

				$result[$column->getId()] = $fetchedTargets[$targetKey];
			} catch (\InvalidArgumentException $e) {
				$this->logger->warning('Invalid relation settings for column ' . $column->getId() . ': ' . $e->getMessage());
			}
		}

		return $result;
	}

	/**
	 * Fetch relation data for relation lookup columns
	 *
	 * @param Column[] $columns
	 * @return array<int, array{column: ?TablesColumn, values: array<int, array{id: int, value: mixed}>}> Relation data grouped by column ID
	 * @throws InternalError
	 */
	private function fetchRelationLookupColumns(array $columns): array {
		$result = [];

		// Batch fetch all needed columns to avoid duplicate queries
		$columnIdsToFetch = $this->collectColumnIdsForLookup($columns);
		$columnsMap = $this->fetchColumnsByIds($columnIdsToFetch);

		foreach ($columns as $column) {
			try {
				$settings = $column->getCustomSettingsObject(RelationLookupSettings::class);
				$relationColumn = $columnsMap[$settings->relationColumnId] ?? null;
				$targetColumn = $columnsMap[$settings->targetColumnId] ?? null;

				if (!$relationColumn || !$targetColumn) {
					continue;
				}

				$lookupData = $this->fetchLookupValues($settings, $relationColumn);
				$result[$column->getId()] = [
					'column' => $targetColumn->jsonSerialize(),
					'values' => $lookupData,
				];
			} catch (\InvalidArgumentException $e) {
				$this->logger->warning('Invalid relation lookup settings for column ' . $column->getId() . ': ' . $e->getMessage());
			}
		}

		return $result;
	}

	private function getCurrentUserId(): string {
		$user = $this->userSession->getUser();
		return $user ? $user->getUID() : 'anonymous';
	}

	/**
	 * Fetch relation values for a specific target
	 *
	 * @param Column $column
	 * @return array<int, array{id: int, value: string}> Indexed per row id
	 * @throws InternalError
	 */
	private function fetchRelationValuesForTarget(Column $column): array {
		$settings = $column->getCustomSettingsObject(RelationSettings::class);

		$cacheKey = $this->buildRelationCacheKey($settings->relationType, $settings->targetId, $settings->labelColumn);
		if (isset($this->cacheRelationData[$cacheKey])) {
			return $this->cacheRelationData[$cacheKey];
		}

		try {
			$targetColumn = $this->columnMapper->find($settings->labelColumn);
			$rows = $this->fetchRowsForTarget($settings, [$settings->labelColumn]);
		} catch (DoesNotExistException $e) {
			$this->cacheRelationData[$cacheKey] = [];
			return [];
		}

		$result = $this->buildRelationValues($rows, $targetColumn, $settings->labelColumn);
		$this->cacheRelationData[$cacheKey] = $result;

		return $result;
	}

	/**
	 * Build a cache key for relation data
	 */
	private function buildRelationCacheKey(string $relationType, int $targetId, int $labelColumn): string {
		return sprintf('%s_%d_%d_%s', $relationType, $targetId, $labelColumn, $this->getCurrentUserId());
	}

	/**
	 * Collect all column IDs needed for lookup processing
	 *
	 * @param Column[] $columns
	 * @return int[]
	 */
	private function collectColumnIdsForLookup(array $columns): array {
		$columnIds = [];

		foreach ($columns as $column) {
			$settings = $column->getCustomSettingsObject(RelationLookupSettings::class);
			$columnIds[] = $settings->targetColumnId;
			$columnIds[] = $settings->relationColumnId;
		}

		return array_unique($columnIds);
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
	 * Fetch lookup values for a relation lookup column
	 *
	 * @param RelationLookupSettings $settings
	 * @param Column $relationColumn
	 * @return array<int, array{id: int, value: mixed}>
	 */
	private function fetchLookupValues(RelationLookupSettings $settings, Column $relationColumn): array {
		$relationSettings = $relationColumn->getCustomSettingsObject(RelationSettings::class);

		$cacheKey = $this->buildRelationCacheKey($relationSettings->relationType, $relationSettings->targetId, $settings->targetColumnId);
		if (isset($this->cacheRelationData[$cacheKey])) {
			return $this->cacheRelationData[$cacheKey];
		}

		try {
			// Fetch both relation label column and lookup target column in a single query
			$rows = $this->fetchRowsForTarget($relationSettings, [$relationSettings->labelColumn, $settings->targetColumnId]);
			$lookupData = $this->buildLookupValues($rows, $relationSettings->labelColumn, $settings->targetColumnId);

			$this->cacheRelationData[$cacheKey] = $lookupData;
			return $lookupData;
		} catch (DoesNotExistException $e) {
			$this->logger->warning('Failed to fetch lookup data: ' . $e->getMessage());
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
				null,
				null,
				$view->getFilterArray(),
				$view->getSortArray(),
				$this->getCurrentUserId()
			);
		}

		return $this->row2Mapper->findAll(
			$columnIds,
			$settings->targetId,
			null,
			null,
			null,
			null,
			$this->getCurrentUserId()
		);
	}

	/**
	 * Build relation values from rows
	 *
	 * @param Row2[] $rows
	 * @param Column $column
	 * @param int $labelColumnId
	 * @return array<int, array{id: int, value: string}>
	 */
	private function buildRelationValues(array $rows, Column $column, int $labelColumnId): array {
		$result = [];

		foreach ($rows as $row) {
			$data = $row->getData();
			$displayFieldData = array_filter($data, fn ($item) => $item['columnId'] === $labelColumnId);
			$value = reset($displayFieldData)['value'] ?? null;

			$rowId = (int)$row->getId();
			$result[$rowId] = [
				'id' => $rowId,
				'value' => $this->formatValue($column, $value),
			];
		}

		return $result;
	}

	/**
	 * Build lookup values from rows containing both relation label and target columns
	 *
	 * @param Row2[] $rows
	 * @param int $relationLabelColumnId
	 * @param int $targetColumnId
	 * @return array<int, array{id: int, value: mixed}>
	 */
	private function buildLookupValues(array $rows, int $relationLabelColumnId, int $targetColumnId): array {
		$result = [];

		foreach ($rows as $row) {
			$data = $row->getData();
			$rowId = (int)$row->getId();

			// Find the relation label value
			$relationLabelData = array_filter($data, fn ($item) => $item['columnId'] === $relationLabelColumnId);
			$relationLabelValue = reset($relationLabelData)['value'] ?? null;

			// Find the target column value
			$targetData = array_filter($data, fn ($item) => $item['columnId'] === $targetColumnId);
			$targetValue = reset($targetData)['value'] ?? null;

			// Only include if relation label exists (meaning this row is referenced)
			if ($relationLabelValue !== null && $relationLabelValue !== '') {
				$result[$rowId] = [
					'id' => $rowId,
					'value' => $targetValue,
				];
			}
		}

		return $result;
	}

	/**
	 * Format a cell value for display as a relation label.
	 * Relation lookup only supports text-line and number columns.
	 */
	private function formatValue(Column $column, mixed $value): string {
		if ($value === null || $value === '' || $value === []) {
			return '';
		}

		return match ($column->getType()) {
			Column::TYPE_TEXT => trim(strip_tags((string)$value)),
			Column::TYPE_NUMBER => $this->formatNumberValue($column, $value),
			default => (string)$value,
		};
	}

	private function formatNumberValue(Column $column, mixed $value): string {
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
