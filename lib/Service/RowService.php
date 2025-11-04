<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Event\RowAddedEvent;
use OCA\Tables\Event\RowDeletedEvent;
use OCA\Tables\Event\RowUpdatedEvent;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Model\RowDataInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ColumnTypes\IColumnTypeBusiness;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesRow from ResponseDefinitions
 */
class RowService extends SuperService {
	private array $tmpRows = []; // holds already loaded rows as a small cache

	public function __construct(
		LoggerInterface $logger,
		?string $userId,
		PermissionsService $permissionsService,
		private ColumnMapper $columnMapper,
		private ViewMapper $viewMapper,
		private TableMapper $tableMapper,
		private Row2Mapper $row2Mapper,
		private IEventDispatcher $eventDispatcher,
		private ColumnsHelper $columnsHelper,
		private ActivityManager $activityManager,
	) {
		parent::__construct($logger, $userId, $permissionsService);

	}

	/**
	 * @param Row2[] $rows
	 * @psalm-return TablesRow[]
	 */
	public function formatRows(array $rows): array {
		return array_map(fn (Row2 $row) => $row->jsonSerialize(), $rows);
	}

	/**
	 * @param int $tableId
	 * @param string $userId
	 * @param ?int $limit
	 * @param ?int $offset
	 * @return Row2[]
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAllByTable(int $tableId, string $userId, ?int $limit = null, ?int $offset = null): array {
		try {
			if ($this->permissionsService->canReadRowsByElementId($tableId, 'table', $userId)) {
				$tableColumns = $this->columnMapper->findAllByTable($tableId);
				$showColumnIds = array_map(fn (Column $column) => $column->getId(), $tableColumns);

				return $this->row2Mapper->findAll($showColumnIds, $tableId, $limit, $offset, null, null, $userId);
			} else {
				throw new PermissionError('no read access to table id = ' . $tableId);
			}
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $viewId
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Row2[]
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	public function findAllByView(int $viewId, string $userId, ?int $limit = null, ?int $offset = null): array {
		try {
			if ($this->permissionsService->canReadRowsByElementId($viewId, 'view', $userId)) {
				$view = $this->viewMapper->find($viewId);

				return $this->row2Mapper->findAll($view->getColumnIds(), $view->getTableId(), $limit, $offset, $view->getFilterArray(), $view->getSortArray(), $userId);
			} else {
				throw new PermissionError('no read access to view id = ' . $viewId);
			}
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}


	/**
	 * @param int $rowId
	 * @return Row2
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function find(int $rowId): Row2 {
		try {
			$tableId = $this->row2Mapper->getTableIdForRow($rowId);

			if (!$this->permissionsService->canReadRowsByElementId($tableId, 'table')) {
				throw new PermissionError('PermissionError: can not read row with id ' . $rowId);
			}

			$columns = $this->columnMapper->findAllByTable($tableId);
			$row = $this->row2Mapper->find($rowId, $columns);
		} catch (Exception|MultipleObjectsReturnedException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (NotFoundError|DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		return $row;
	}

	/**
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @param RowDataInput|list<array{columnId: int, value: mixed}> $data
	 * @return Row2
	 *
	 * @throws BadRequestError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 * @throws InternalError
	 */
	public function create(?int $tableId, ?int $viewId, RowDataInput|array $data): Row2 {
		if ($this->userId === null || $this->userId === '') {
			$e = new \Exception('No user id in context, but needed.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$view = null;

		if ($viewId) {
			try {
				$view = $this->viewMapper->find($viewId);
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError('Given view could not be found. More details can be found in the log.');
			} catch (InternalError|Exception|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}

			// security
			if (!$this->permissionsService->canCreateRows($view)) {
				throw new PermissionError('create row at the view id = ' . $viewId . ' is not allowed.');
			}

			$columns = $this->columnMapper->findAll($view->getColumnIds());
		}
		if ($tableId) {
			try {
				$table = $this->tableMapper->find($tableId);
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError('Given table could not be found. More details can be found in the log.');
			} catch (MultipleObjectsReturnedException|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}

			// security
			if (!$this->permissionsService->canCreateRows($table, 'table')) {
				throw new PermissionError('create row at the table id = ' . $tableId . ' is not allowed.');
			}

			$columns = $this->columnMapper->findAllByTable($tableId);
		}

		if (!$viewId && !$tableId) {
			throw new InternalError('Cannot create row without table or view in context');
		}

		$tableId = $tableId ?? $view->getTableId();

		$data = $data instanceof RowDataInput ? $data : RowDataInput::fromArray($data);
		$data = $this->cleanupAndValidateData($data, $columns, $tableId, $viewId);
		$data = $this->enhanceWithViewDefaults($view, $data);

		$tableId = $tableId ?? $view->getTableId();
		$row2 = new Row2();
		$row2->setTableId($tableId);
		$row2->setData($data);
		try {
			$insertedRow = $this->row2Mapper->insert($row2);

			$this->eventDispatcher->dispatchTyped(new RowAddedEvent($insertedRow));
			$this->activityManager->triggerEvent(
				objectType: ActivityManager::TABLES_OBJECT_ROW,
				object: $insertedRow,
				subject: ActivityManager::SUBJECT_ROW_CREATE,
				author: $this->userId,
			);

			return $this->filterRowResult($view, $insertedRow);
		} catch (InternalError|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
	}

	/**
	 * When inserting rows into views we try to prefill columns that are not accessible by reasonable defaults
	 *
	 * This might not work in all cases, but for single filter rules this is the sanest to ensure the row is actually part of the view
	 */
	private function enhanceWithViewDefaults(?View $view, RowDataInput $data): RowDataInput {
		if ($view === null) {
			return $data;
		}

		$filters = $view->getFilterArray();
		if (empty($filters)) {
			return $data;
		}

		// Process each filter rule group (AND groups)
		foreach ($filters as $filterRules) {
			if (!is_array($filterRules)) {
				continue;
			}

			// Process each filter within the group (OR conditions)
			foreach ($filterRules as $filter) {
				if (!is_array($filter) || !isset($filter['columnId'], $filter['operator'], $filter['value'])) {
					continue;
				}

				// Skip if the column is already visible in the view
				if (in_array($filter['columnId'], $view->getColumnIds())) {
					continue;
				}

				// For meta columns, we don't need to add them to the data since they are handled separately
				if (Column::isValidMetaTypeId($filter['columnId'])) {
					continue;
				}

				// Only handle simple equality filters for now
				if (!in_array($filter['operator'], ['is-equal', 'is-not-equal'])) {
					continue;
				}

				// Only set the default if the column hasn't been set yet
				if (!$data->hasColumn($filter['columnId'])) {
					$data->add($filter['columnId'], $this->columnsHelper->resolveSearchValue((string)$filter['value'], $this->userId));
				}
			}
		}
		return $data;
	}

	/**
	 * @return array<int, true>
	 */
	private function extractColumnsByProperty(View $view, string $property): array {
		return array_reduce(
			$view->getColumnsSettingsArray(),
			static function (array $carry, ViewColumnInformation $column) use ($property) {
				if (method_exists($column, $property) && $column->{$property}()) {
					$carry[$column->getId()] = true;
				}
				return $carry;
			},
			[]
		);
	}

	/**
	 * @param RowDataInput $data
	 * @throws InternalError
	 * @throws BadRequestError
	 * @return RowDataInput
	 */
	private function cleanupAndValidateData(RowDataInput $data, array $columns, ?int $tableId, ?int $viewId, ?int $rowId = null): RowDataInput {
		$view = $viewId ? $this->viewMapper->find($viewId) : null;
		$readOnlyColumns = $view ? $this->extractColumnsByProperty($view, 'isReadonly') : [];
		$mandatoryColumns = $view ? $this->extractColumnsByProperty($view, 'isMandatory') : [];

		$out = new RowDataInput();
		foreach ($data as $entry) {
			$columnId = (int)$entry['columnId'];

			// Skip metadata columns
			if (Column::isValidMetaTypeId($columnId)) {
				continue;
			}

			$column = $this->getColumnFromColumnsArray($columnId, $columns);

			if ($column) {
				$columnBusiness = $this->getColumnBusiness($column);
				$columnBusiness->validateValue($entry['value'], $column, $this->userId, $tableId, $rowId);
			}

			// check if it is allowed to insert a value for the requested column
			if (!$column && $viewId) {
				throw new InternalError('Column with id ' . $entry['columnId'] . ' is not part of view with id ' . $viewId);
			} elseif (!$column && $tableId) {
				throw new InternalError('Column with id ' . $entry['columnId'] . ' is not part of table with id ' . $tableId);
			}

			if (!$column) {
				$e = new \Exception('No column found, can not parse value.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}

			if (!empty($readOnlyColumns[$entry['columnId']])) {
				continue;
			}

			// parse given value to respect the column type value format
			$out->add((int)$entry['columnId'], $this->parseValueByColumnType($column, $entry['value']));
		}

		if ($viewId && !empty($mandatoryColumns)) {
			$existingRow = null;
			if ($rowId !== null) {
				try {
					$existingRow = $this->getRowById($rowId);
				} catch (NotFoundError|InternalError $e) {
					$this->logger->debug('Could not load existing row for mandatory validation', ['rowId' => $rowId, 'exception' => $e]);
				}
			}
			$this->validateMandatoryColumns($mandatoryColumns, $out, $columns, $existingRow);
		}

		return $out;
	}

	/**
	 * @param array<int, bool> $mandatoryColumns
	 * @param RowDataInput $data
	 * @param Column[] $columns
	 * @param Row2|null $existingRow
	 * @throws BadRequestError
	 * @throws InternalError
	 */
	private function validateMandatoryColumns(array $mandatoryColumns, RowDataInput $data, array $columns, ?Row2 $existingRow = null): void {
		foreach ($mandatoryColumns as $columnId => $isMandatory) {
			if (!$isMandatory) {
				continue;
			}

			$column = $this->getColumnFromColumnsArray($columnId, $columns);
			if (!$column) {
				continue;
			}

			$hasValue = false;
			$value = null;

			foreach ($data as $entry) {
				if ($entry['columnId'] === $columnId) {
					$value = $entry['value'];
					$hasValue = true;
					break;
				}
			}

			if (!$hasValue && $existingRow !== null) {
				foreach ($existingRow->getData() as $existingEntry) {
					if ($existingEntry['columnId'] === $columnId) {
						$value = $existingEntry['value'];
						$hasValue = true;
						break;
					}
				}
			}

			if ($hasValue) {
				try {
					$columnBusiness = $this->getColumnBusiness($column);
					$isValid = $this->isValueValidForMandatoryColumn($value, $column, $columnBusiness);
					if (!$isValid) {
						throw new BadRequestError(
							'Mandatory column "' . $column->getTitle() . '" cannot be empty or invalid.'
						);
					}
				} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
					$this->logger->debug('Column type business class not found for mandatory validation', ['exception' => $e]);
				}
			} else {
				throw new BadRequestError(
					'Mandatory column "' . $column->getTitle() . '" cannot be empty.'
				);
			}
		}
	}

	private function isValueValidForMandatoryColumn($value, Column $column, IColumnTypeBusiness $columnBusiness): bool {
		if ($column->getSubtype() === Column::SUBTYPE_SELECTION_CHECK) {
			return true;
		}

		if ($value === null || $value === '') {
			return false;
		}
		if ($column->getType() === Column::TYPE_SELECTION) {
			if (is_array($value)) {
				return count($value) > 0;
			}
			if (is_numeric($value) && $value > 0) {
				return true;
			}
			return $column->getSelectionDefault() !== null && $column->getSelectionDefault() !== '';
		}
		if ($column->getSubtype() === Column::SUBTYPE_SELECTION_MULTI) {
			if (is_array($value)) {
				return count($value) > 0;
			}
			$defaultValue = $column->getSelectionDefault();
			return $defaultValue !== null && $defaultValue !== '' && $defaultValue !== '[]';
		}
		return $value !== null && $value !== '' && $value !== [];
	}

	/**
	 * @param Column $column
	 * @param string|array|int|float|bool|null $value
	 * @return array|string|int|float|null
	 */
	private function parseValueByColumnType(Column $column, $value = null) {
		try {
			$columnBusiness = $this->getColumnBusiness($column);
			if ($columnBusiness->canBeParsed($value, $column)) {
				return json_decode($columnBusiness->parseValue($value, $column), true);
			}
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type business class not found', ['exception' => $e]);
		}
		$this->logger->warning('Value could not be parsed for column ' . $column->getTitle(), [$value]);
		return null;
	}

	/**
	 * @param int $columnId
	 * @param Column[] $columns
	 * @return Column|null
	 */
	private function getColumnFromColumnsArray(int $columnId, array $columns): ?Column {
		foreach ($columns as $column) {
			if ($column->getId() === $columnId) {
				return $column;
			}
		}
		return null;
	}

	/**
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	private function getRowById(int $rowId): Row2 {
		if (isset($this->tmpRows[$rowId])) {
			return $this->tmpRows[$rowId];
		}

		try {
			if ($this->row2Mapper->getTableIdForRow($rowId) === null) {
				$e = new \Exception('No table id in row, but needed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			$row = $this->row2Mapper->find($rowId, $this->columnMapper->findAllByTable($this->row2Mapper->getTableIdForRow($rowId)));
			$row->markAsLoaded();
		} catch (InternalError|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		$this->tmpRows[$rowId] = $row;
		return $row;
	}

	/**
	 * Update multiple cells in a row
	 *
	 * @param int $id
	 * @param int|null $viewId
	 * @param list<array{columnId: string|int, value: mixed}> $data
	 * @param string $userId
	 * @return Row2
	 *
	 * @throws BadRequestError
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @noinspection DuplicatedCode
	 */
	public function updateSet(
		int $id,
		?int $viewId,
		array $data,
		string $userId,
		?int $tableId,
	): Row2 {
		try {
			$item = $this->getRowById($id);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		if ($viewId) {
			// security
			if (!$this->permissionsService->canReadRowsByElementId($viewId, 'view', $userId)) {
				$e = new \Exception('Row not found.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			if (!$this->permissionsService->canUpdateRowsByViewId($viewId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new PermissionError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}

			try {
				$view = $this->viewMapper->find($viewId);
			} catch (InternalError|MultipleObjectsReturnedException|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}

			// is row in view?
			if (!$this->row2Mapper->isRowInViewPresent($id, $view, $userId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}

			// fetch all needed columns
			try {
				$columns = $this->columnMapper->findAll($view->getColumnIds());
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		} else {
			if ($tableId === null) {
				$tableId = $item->getTableId();
			}
			if ($tableId !== $item->getTableId()) {
				$e = new \Exception('Row does not belong to table with id ' . $tableId);
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}

			// security
			if (!$this->permissionsService->canReadRowsByElementId($item->getTableId(), 'table', $userId)) {
				$e = new \Exception('Row not found.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			if (!$this->permissionsService->canUpdateRowsByTableId($tableId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new PermissionError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			try {
				$columns = $this->columnMapper->findAllByTable($tableId);
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		}

		$previousData = $item->getData();
		$data = RowDataInput::fromArray($data);
		$data = $this->cleanupAndValidateData($data, $columns, $item->getTableId(), $viewId, $id);

		foreach ($data as $entry) {
			// Check whether the column of which the value should change is part of the table / view
			$column = $this->getColumnFromColumnsArray($entry['columnId'], $columns);
			if ($column) {
				$item->insertOrUpdateCell($entry);
			} else {
				$this->logger->warning('Column to update row not found, will continue and ignore this.');
			}
		}

		$updatedRow = $this->row2Mapper->update($item);

		$this->eventDispatcher->dispatchTyped(new RowUpdatedEvent($updatedRow, $previousData));

		if ($updatedRow->getData() !== $previousData) {
			$this->activityManager->triggerEvent(
				objectType: ActivityManager::TABLES_OBJECT_ROW,
				object: $updatedRow,
				subject: ActivityManager::SUBJECT_ROW_UPDATE,
				author: $this->userId,
				additionalParams: [
					'before' => $previousData,
					'after' => $updatedRow->getData(),
				]
			);
		}

		return $this->filterRowResult($view ?? null, $updatedRow);
	}

	/**
	 * @param int $id
	 * @param int|null $viewId
	 * @param string $userId
	 * @return Row2
	 *
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @noinspection DuplicatedCode
	 */
	public function delete(int $id, ?int $viewId, string $userId): Row2 {
		try {
			$item = $this->getRowById($id);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		if ($viewId) {
			// security
			if (!$this->permissionsService->canReadRowsByElementId($viewId, 'view', $userId)) {
				$e = new \Exception('Row not found.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			if (!$this->permissionsService->canDeleteRowsByViewId($viewId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new PermissionError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			try {
				$view = $this->viewMapper->find($viewId);
			} catch (InternalError|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			if (!$this->row2Mapper->isRowInViewPresent($id, $view, $userId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		} else {
			// security
			if (!$this->permissionsService->canReadRowsByElementId($item->getTableId(), 'table', $userId)) {
				$e = new \Exception('Row not found.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			if (!$this->permissionsService->canDeleteRowsByTableId($item->getTableId())) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new PermissionError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		}

		try {
			$deletedRow = $this->row2Mapper->delete($item);

			$event = new RowDeletedEvent($item, $item->getData());

			$this->eventDispatcher->dispatchTyped($event);
			$this->activityManager->triggerEvent(
				objectType: ActivityManager::TABLES_OBJECT_ROW,
				object: $deletedRow,
				subject: ActivityManager::SUBJECT_ROW_DELETE,
				author: $this->userId,
			);

			return $this->filterRowResult($view ?? null, $deletedRow);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
	}

	/**
	 * @param int $tableId
	 * @param null|string $userId
	 *
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function deleteAllByTable(int $tableId, ?string $userId = null): void {
		// security
		if (!$this->permissionsService->canDeleteRowsByTableId($tableId, $userId)) {
			throw new PermissionError('delete all rows for table id = ' . $tableId . ' is not allowed.');
		}

		$columns = $this->columnMapper->findAllByTable($tableId);

		$this->row2Mapper->deleteAllForTable($tableId, $columns);
	}

	/**
	 * This deletes all data for a column, eg if the columns gets removed
	 *
	 * >>> SECURITY <<<
	 * We do not check if you are allowed to remove this data. That has to be done before!
	 * Why? Mostly this check will have be run before and we can pass this here due to performance reasons.
	 *
	 * @param Column $column
	 * @throws InternalError
	 */
	public function deleteColumnDataFromRows(Column $column):void {
		$this->row2Mapper->deleteDataForColumn($column);
	}

	/**
	 * @param int $tableId
	 * @return int
	 *
	 * @throws PermissionError
	 */
	public function getRowsCount(int $tableId): int {
		if ($this->permissionsService->canReadRowsByElementId($tableId, 'table')) {
			return $this->row2Mapper->countRowsForTable($tableId);
		} else {
			throw new PermissionError('no read access for counting to table id = ' . $tableId);
		}
	}

	/**
	 * @param View $view
	 * @param string $userId
	 * @return int
	 *
	 * @throws PermissionError
	 */
	public function getViewRowsCount(View $view, string $userId): int {
		if ($this->permissionsService->canReadRowsByElementId($view->getId(), 'view', $userId)) {
			try {
				return $this->row2Mapper->countRowsForView($view, $userId);
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				return 0;
			}
		} else {
			throw new PermissionError('no read access for counting to view id = ' . $view->getId());
		}
	}

	/**
	 * @param int $rowId
	 * @param int $viewId
	 * @param string $userId
	 * @return bool
	 *
	 * @throws PermissionError
	 */
	public function isRowInViewPresent(int $rowId, int $viewId, string $userId): bool {
		if (!$this->permissionsService->canReadRowsByElementId($viewId, 'view', $userId)) {
			$e = new \Exception('Row not found.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new PermissionError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$view = $this->viewMapper->find($viewId);
		return $this->row2Mapper->isRowInViewPresent($rowId, $view, $userId);
	}

	private function filterRowResult(?View $view, Row2 $row): Row2 {
		if ($view === null) {
			return $row;
		}

		$row->filterDataByColumns($view->getColumnIds());

		return $row;
	}

	private function getColumnBusiness(Column $column): IColumnTypeBusiness {
		$businessClassName = 'OCA\Tables\Service\ColumnTypes\\';
		$businessClassName .= ucfirst($column->getType()) . ucfirst($column->getSubtype()) . 'Business';
		/** @var IColumnTypeBusiness $columnBusiness */
		$columnBusiness = Server::get($businessClassName);

		return $columnBusiness;
	}
}
