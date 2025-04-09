<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use DateTime;
use Exception;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesColumn from ResponseDefinitions
 */
class ColumnService extends SuperService {
	private IL10N $l;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		private ColumnMapper $mapper,
		private TableMapper $tableMapper,
		private ViewService $viewService,
		private RowService $rowService,
		IL10N $l,
		private UserHelper $userHelper,
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->l = $l;
	}


	/**
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAllByTable(int $tableId, ?int $viewId = null, ?string $userId = null): array {
		if ($this->permissionsService->canReadColumnsByTableId($tableId, $userId) || ($viewId != null && $this->permissionsService->canReadColumnsByViewId($viewId, $userId))) {
			try {
				return $this->enhanceColumns($this->mapper->findAllByTable($tableId));
			} catch (\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		} else {
			throw new PermissionError('no read access to table id = ' . $tableId);
		}
	}

	/**
	 * @param int $viewId
	 * @param string|null $userId
	 * @return array
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws InternalError
	 */
	public function findAllByView(int $viewId, ?string $userId = null): array {
		// No need to check for columns outside the view since they cannot be addressed
		try {
			$view = $this->viewService->find($viewId, true, $userId);
		} catch (InternalError|MultipleObjectsReturnedException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new PermissionError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		$viewColumnIds = $view->getColumnsArray();
		$viewColumns = $this->mapper->findAll($viewColumnIds);
		return $this->enhanceColumns($viewColumns);
	}

	/**
	 * @param Column[] $columns
	 * @return TablesColumn[]
	 */
	public function formatColumns(array $columns): array {
		return array_map(fn (Column $item) => $item->jsonSerialize(), $columns);
	}

	/**
	 * @throws NotFoundError
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function find(int $id, ?string $userId = null): Column {
		try {
			/** @var Column $column */
			$column = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canReadColumnsByTableId($column->getTableId(), $userId)) {
				throw new PermissionError('PermissionError: can not read column with id ' . $id);
			}

			return $this->enhanceColumn($column);
		} catch (DoesNotExistException $e) {
			$this->logger->warning($e->getMessage());
			throw new NotFoundError($e->getMessage());
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @noinspection DuplicatedCode
	 *
	 * @param string|null $userId
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @param ColumnDto $columnDto
	 * @param array $selectedViewIds
	 * @return Column
	 *
	 * @throws InternalError
	 * @throws PermissionError|NotFoundError
	 */
	public function create(
		?string $userId,
		?int $tableId,
		?int $viewId,
		ColumnDto $columnDto,
		array $selectedViewIds = [],
	):Column {
		// security
		if ($viewId) {
			try {
				$view = $this->viewService->find($viewId);
			} catch (InternalError|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			} catch (PermissionError $e) {
				throw new PermissionError('Can not load given view, no permission.');
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			try {
				$table = $this->tableMapper->find($view->getTableId());
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		} elseif ($tableId) {
			try {
				$table = $this->tableMapper->find($tableId);
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		} else {
			throw new InternalError('Cannot create column without table or view in context');
		}

		if (!$this->permissionsService->canCreateColumns($table)) {
			throw new PermissionError('create column for the table id = ' . $table->getId() . ' is not allowed.');
		}

		// Add number to title to avoid duplicate
		$columns = $this->mapper->findAllByTable($table->getId());
		$i = 1;
		$newTitle = $columnDto->getTitle();
		while (true) {
			$found = false;
			foreach ($columns as $column) {
				if ($column->getTitle() === $newTitle) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				break;
			}
			$newTitle = $columnDto->getTitle() . ' (' . $i . ')';
			$i++;
		}

		$time = new DateTime();
		$item = Column::fromDto($columnDto);
		$item->setTitle($newTitle);
		$item->setTableId($table->getId());
		$this->updateMetadata($item, $userId, true);

		try {
			$entity = $this->mapper->insert($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		if (isset($view) && $view) {
			// Add columns to view(s)
			$this->viewService->update($view->getId(), ['columns' => json_encode(array_merge($view->getColumnsArray(), [$entity->getId()]))], $userId, true);
		}
		foreach ($selectedViewIds as $viewId) {
			try {
				$view = $this->viewService->find($viewId);
			} catch (InternalError|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			} catch (PermissionError) {
				throw new PermissionError('Can not add column to view, no permission.');
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			$this->viewService->update($viewId, ['columns' => json_encode(array_merge($view->getColumnsArray(), [$entity->getId()]))], $userId, true);
		}
		return $this->enhanceColumn($entity);
	}

	/**
	 * @noinspection DuplicatedCode
	 * @param int $columnId
	 * @param int|null $tableId
	 * @param string|null $userId
	 * @param ColumnDto $columnDto
	 * @return Column
	 * @throws InternalError
	 */
	public function update(
		int $columnId,
		?int $tableId,
		?string $userId,
		ColumnDto $columnDto,
	):Column {
		try {
			/** @var Column $item */
			$item = $this->mapper->find($columnId);

			// security
			if (!$this->permissionsService->canUpdateColumnsByTableId($item->getTableId())) {
				throw new PermissionError('update column id = ' . $columnId . ' is not allowed.');
			}

			if ($columnDto->getTitle() !== null) {
				$item->setTitle($columnDto->getTitle());
			}
			if ($tableId !== null) {
				$item->setTableId($tableId);
			}
			if ($columnDto->getType() !== null) {
				$item->setType($columnDto->getType());
			}
			if ($columnDto->getSubtype() !== null) {
				$item->setSubtype($columnDto->getSubtype());
			}
			if ($columnDto->getNumberPrefix() !== null) {
				$item->setNumberPrefix($columnDto->getNumberPrefix());
			}
			if ($columnDto->getNumberSuffix() !== null) {
				$item->setNumberSuffix($columnDto->getNumberSuffix());
			}
			if ($columnDto->isMandatory() !== null) {
				$item->setMandatory($columnDto->isMandatory());
			}
			$item->setDescription($columnDto->getDescription());
			$item->setTextDefault($columnDto->getTextDefault());
			$item->setTextAllowedPattern($columnDto->getTextAllowedPattern());
			$item->setTextMaxLength($columnDto->getTextMaxLength());
			$item->setNumberDefault($columnDto->getNumberDefault());
			$item->setNumberMin($columnDto->getNumberMin());
			$item->setNumberMax($columnDto->getNumberMax());
			$item->setNumberDecimals($columnDto->getNumberDecimals());
			if ($columnDto->getSelectionOptions() !== null) {
				$item->setSelectionOptions($columnDto->getSelectionOptions());
			}
			if ($columnDto->getSelectionDefault() !== null) {
				$item->setSelectionDefault($columnDto->getSelectionDefault());
			}
			$item->setDatetimeDefault($columnDto->getDatetimeDefault());

			if ($columnDto->getUsergroupDefault() !== null) {
				$item->setUsergroupDefault($columnDto->getUsergroupDefault());
			}
			$item->setUsergroupMultipleItems($columnDto->getUsergroupMultipleItems());
			$item->setUsergroupSelectUsers($columnDto->getUsergroupSelectUsers());
			$item->setUsergroupSelectGroups($columnDto->getUsergroupSelectGroups());
			$item->setUsergroupSelectTeams($columnDto->getUsergroupSelectTeams());
			$item->setShowUserStatus($columnDto->getShowUserStatus());

			$this->updateMetadata($item, $userId);
			return $this->enhanceColumn($this->mapper->update($item));
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	private function updateMetadata(Column $column, ?string $userId, bool $setCreateData = false): void {
		if ($userId) {
			$column->setLastEditBy($userId);
		} else {
			if ($this->userId) {
				$column->setLastEditBy($this->userId);
			} else {
				$e = new Exception('Could not update LastEditBy, no user id given.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
		$time = new DateTime();
		$column->setLastEditAt($time->format('Y-m-d H:i:s'));

		if ($setCreateData) {
			$column->setCreatedAt($time->format('Y-m-d H:i:s'));
			if ($userId) {
				$column->setCreatedBy($userId);
			} else {
				if ($this->userId) {
					$column->setCreatedBy($this->userId);
				} else {
					$e = new Exception('Could not update CreatedBy, no user id given.');
					$this->logger->error($e->getMessage(), ['exception' => $e]);
				}
			}
		}
	}

	/**
	 * @param int $id
	 * @param bool $skipRowCleanup
	 * @param null|string $userId
	 * @return Column
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function delete(int $id, bool $skipRowCleanup = false, ?string $userId = null): Column {
		try {
			/** @var Column $item */
			$item = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security
		if (!$this->permissionsService->canDeleteColumnsByTableId($item->getTableId(), $userId)) {
			throw new PermissionError('delete column id = ' . $id . ' is not allowed.');
		}

		if (!$skipRowCleanup) {
			try {
				$this->rowService->deleteColumnDataFromRows($item);
			} catch (InternalError $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			try {
				$table = $this->tableMapper->find($item->getTableId());
			} catch (DoesNotExistException|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			$this->viewService->deleteColumnDataFromViews($id, $table);
		}

		try {
			$this->mapper->delete($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		return $this->enhanceColumn($item);
	}

	/**
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @param array $titles example ['Test column 1', 'And so on', '3rd column title']
	 * @param array $dataTypes example ['datetime', 'number', 'text']
	 * @param string|null $userId
	 * @param bool $createUnknownColumns
	 * @param int $countCreatedColumns
	 * @param int $countMatchingColumns
	 * @return array with column object or null for given columns
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function findOrCreateColumnsByTitleForTableAsArray(?int $tableId, ?int $viewId, array $titles, array $dataTypes, ?string $userId, bool $createUnknownColumns, int &$countCreatedColumns, int &$countMatchingColumns): array {
		$result = [];

		if ($userId === null) {
			$userId = $this->userId;
		}
		if ($viewId) {
			$allColumns = $this->findAllByView($viewId, $userId);
		} elseif ($tableId) {
			$allColumns = $this->findAllByTable($tableId, null, $userId);
		} else {
			$e = new Exception('Either tableId nor viewId is given.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$i = -1;
		foreach ($titles as $title) {
			$i++;
			foreach ($allColumns as $column) {
				if ($column->getTitle() === $title) {
					$result[$i] = $column;
					$countMatchingColumns++;
					continue 2;
				}
				$result[$i] = '';
			}
			// if there are no columns at all
			if (!isset($result[$i])) {
				$result[$i] = '';
			}
			// if column was not found
			if ($result[$i] === '' && $createUnknownColumns) {
				$description = $this->l->t('This column was automatically created by the import service.');
				$result[$i] = $this->create(
					$userId,
					$tableId,
					$viewId,
					new ColumnDto(
						title: $title,
						type: $dataTypes[$i]['type'],
						subtype: $dataTypes[$i]['subtype'] ?? '',
						mandatory: false,
						description: $description,
						numberDecimals: $dataTypes[$i]['number_decimals'] ?? null,
						numberPrefix: $dataTypes[$i]['number_prefix'] ?? null,
						numberSuffix: $dataTypes[$i]['number_suffix'] ?? null,
						selectionDefault: $dataTypes[$i]['selection_default'] ?? null
					),
				);
				$countCreatedColumns++;
			}
		}
		return $this->enhanceColumns($result);
	}

	/**
	 * @param int $tableId
	 * @return int
	 * @throws PermissionError
	 */
	public function getColumnsCount(int $tableId): int {
		if ($this->permissionsService->canReadColumnsByTableId($tableId)) {
			return $this->mapper->countColumns($tableId);
		} else {
			throw new PermissionError('no read access for counting to table id = ' . $tableId);
		}
	}

	/**
	 * add some basic values related to this column in context
	 *
	 * $userId can be set or ''
	 * @param Column $column
	 *
	 * @return Column
	 */
	private function enhanceColumn(Column $column): Column {
		// add created by display name for UI usage
		$column->setCreatedByDisplayName($this->userHelper->getUserDisplayName($column->getCreatedBy()));
		$column->setLastEditByDisplayName($this->userHelper->getUserDisplayName($column->getLastEditBy()));
		return $column;
	}

	private function enhanceColumns(?array $columns): array {
		if ($columns === null) {
			return [];
		}

		foreach ($columns as $column) {
			if ($column instanceof Column) {
				$this->enhanceColumn($column);
			}
		}
		return $columns;
	}
}
