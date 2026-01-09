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

		$target = sprintf('%s_%s_%s',
			$settings['relationType'],
			$settings['targetId'],
			$settings['labelColumn']
		);

		return $this->getRelationDataForTarget($target, $column);
	}

	/**
	 * Get relation data for a specific target
	 *
	 * @param string $target
	 * @param Column $column
	 * @return array
	 * @throws InternalError
	 */
	private function getRelationDataForTarget(string $target, Column $column): array {
		// Check cache first
		$cacheKey = $target . '_' . ($this->userId ?? 'anonymous');
		if (isset($this->cacheRelationData[$cacheKey])) {
			return $this->cacheRelationData[$cacheKey];
		}

		$settings = $column->getCustomSettingsArray();
		if (empty($settings['relationType']) || empty($settings['targetId']) || empty($settings['labelColumn'])) {
			$this->cacheRelationData[$cacheKey] = [];
			return [];
		}

		$isView = $settings['relationType'] === 'view';
		$targetId = $settings['targetId'] ?? null;

		try {
			$targetColumn = $this->columnMapper->find($settings['labelColumn']);
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
				return $item['columnId'] === (int)$settings['labelColumn'];
			});
			$value = reset($displayFieldData)['value'] ?? null;

			// Structure compatible with Row2 format: {id: int, label: string}
			$id = $row->getId();
			$result[$id] = [
				'id' => $id,
				'label' => $value,
			];
		}

		$this->cacheRelationData[$cacheKey] = $result;
		return $result;
	}
}
