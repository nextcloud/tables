<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\LegacyRowMapper;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ColumnTypes\IColumnTypeBusiness;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesRow from ResponseDefinitions
 */
class RowService extends SuperService {
	private LegacyRowMapper $mapper;
	private ColumnMapper $columnMapper;
	private ViewMapper $viewMapper;
	private TableMapper $tableMapper;
	private Row2Mapper $row2Mapper;
	private array $tmpRows = []; // holds already loaded rows as a small cache

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
		LegacyRowMapper    $mapper, ColumnMapper $columnMapper, ViewMapper $viewMapper, TableMapper $tableMapper, Row2Mapper $row2Mapper) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
		$this->columnMapper = $columnMapper;
		$this->viewMapper = $viewMapper;
		$this->tableMapper = $tableMapper;
		$this->row2Mapper = $row2Mapper;
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
				return $this->row2Mapper->findAll($this->columnMapper->findAllByTable($tableId), $tableId, $limit, $offset, null, null, $userId);

				// return $this->mapper->findAllByTable($tableId, $limit, $offset);
			} else {
				throw new PermissionError('no read access to table id = '.$tableId);
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
				$columnsArray = $view->getColumnsArray();
				$columns = $this->columnMapper->findAll($columnsArray);
				return $this->row2Mapper->findAll($columns, $view->getTableId(), $limit, $offset, $view->getFilterArray(), $view->getSortArray(), $userId);

				// return $this->mapper->findAllByView($this->viewMapper->find($viewId), $userId, $limit, $offset);
			} else {
				throw new PermissionError('no read access to view id = '.$viewId);
			}
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}


	/**
	 * @param int $id
	 * @return Row2
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function find(int $id): Row2 {
		try {
			$columns = $this->columnMapper->findAllByTable($id);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		try {
			$row = $this->row2Mapper->find($id, $columns);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		// security
		if (!$this->permissionsService->canReadRowsByElementId($row->getTableId(), 'table')) {
			throw new PermissionError('PermissionError: can not read row with id '.$id);
		}

		return $row;
	}

	/**
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @param list<array{columnId: int, value: mixed}> $data
	 * @return Row2
	 *
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 * @throws InternalError
	 */
	public function create(?int $tableId, ?int $viewId, array $data): Row2 {
		if ($this->userId === null || $this->userId === '') {
			$e = new \Exception('No user id in context, but needed.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		if ($viewId) {
			try {
				$view = $this->viewMapper->find($viewId);
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError("Given view could not be found. More details can be found in the log.");
			} catch (InternalError|Exception|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}

			// security
			if (!$this->permissionsService->canCreateRows($view)) {
				throw new PermissionError('create row at the view id = '.$viewId.' is not allowed.');
			}

			$columns = $this->columnMapper->findMultiple($view->getColumnsArray());
		} elseif ($tableId) {
			try {
				$table = $this->tableMapper->find($tableId);
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError("Given table could not be found. More details can be found in the log.");
			} catch (MultipleObjectsReturnedException|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}

			// security
			if (!$this->permissionsService->canCreateRows($table, 'table')) {
				throw new PermissionError('create row at the table id = '.$tableId.' is not allowed.');
			}

			$columns = $this->columnMapper->findAllByTable($tableId);
		} else {
			throw new InternalError('Cannot create row without table or view in context');
		}

		$data = $this->cleanupData($data, $columns, $tableId, $viewId);

		// perf
		$row2 = new Row2();
		$row2->setTableId($tableId);
		$row2->setData($data);
		try {
			return $this->row2Mapper->insert($row2, $this->columnMapper->findAllByTable($tableId));
		} catch (InternalError|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		/*$time = new DateTime();
		$item = new Row();
		$item->setDataArray($data);
		$item->setTableId($viewId ? $view->getTableId() : $tableId);
		$item->setCreatedBy($this->userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditBy($this->userId);
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		return $this->mapper->insert($item);*/
	}

	/**
	 * @param list<array{columnId: string|int, value: mixed}> $data
	 * @param Column[] $columns
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @return list<array{columnId: int, value: float|int|string|null}>
	 *
	 * @throws InternalError
	 */
	private function cleanupData(array $data, array $columns, ?int $tableId, ?int $viewId): array {
		$out = [];
		foreach ($data as $entry) {
			$column = $this->getColumnFromColumnsArray((int) $entry['columnId'], $columns);

			// check if it is allowed to insert a value for the requested column
			if (!$column && $viewId) {
				throw new InternalError('Column with id '.$entry['columnId'].' is not part of view with id '.$viewId);
			} elseif (!$column && $tableId) {
				throw new InternalError('Column with id '.$entry['columnId'].' is not part of table with id '.$tableId);
			}

			if (!$column) {
				$e = new \Exception('No column found, can not parse value.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}

			// parse given value to respect the column type value format
			$out[] = [
				'columnId' => (int) $entry['columnId'],
				'value' => $this->parseValueByColumnType($column, $entry['value'])
			];
		}
		return $out;
	}

	/**
	 * @param Column $column
	 * @param string|array|int|float|bool|null $value
	 * @return string|int|float|null
	 */
	private function parseValueByColumnType(Column $column, $value = null) {
		try {
			$businessClassName = 'OCA\Tables\Service\ColumnTypes\\';
			$businessClassName .= ucfirst($column->getType()).ucfirst($column->getSubtype()).'Business';
			/** @var IColumnTypeBusiness $columnBusiness */
			$columnBusiness = Server::get($businessClassName);
			if($columnBusiness->canBeParsed($value, $column)) {
				return json_decode($columnBusiness->parseValue($value, $column));
			}
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type business class not found', ['exception' => $e]);
		}
		$this->logger->warning('Value could not be parsed for column '.$column->getTitle(), [$value]);
		return null;
	}

	/**
	 * @param int $columnId
	 * @param Column[] $columns
	 * @return Column|null
	 */
	private function getColumnFromColumnsArray(int $columnId, array $columns): ?Column {
		foreach ($columns as $column) {
			if($column->getId() === $columnId) {
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
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			$row = $this->row2Mapper->find($rowId, $this->columnMapper->findAllByTable($this->row2Mapper->getTableIdForRow($rowId)));
			$row->markAsLoaded();
		} catch (InternalError|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
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
	 * @throws InternalError
	 * @throws NotFoundError
	 * @noinspection DuplicatedCode
	 */
	public function updateSet(
		int $id,
		?int $viewId,
		array $data,
		string $userId
	): Row2 {
		try {
			$item = $this->getRowById($id);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		if ($viewId) {
			// security
			if (!$this->permissionsService->canUpdateRowsByViewId($viewId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}

			try {
				$view = $this->viewMapper->find($viewId);
			} catch (InternalError|MultipleObjectsReturnedException|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}

			// is row in view?
			if($this->row2Mapper->isRowInViewPresent($id, $view, $userId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}

			// fetch all needed columns
			try {
				$columns = $this->columnMapper->findMultiple($view->getColumnsArray());
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		} else {
			// if no view id is set, we assume a table and take the tableId from the row
			$tableId = $item->getTableId();

			// security
			if (!$this->permissionsService->canUpdateRowsByTableId($tableId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			try {
				$columns = $this->columnMapper->findAllByTable($tableId);
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		}

		$data = $this->cleanupData($data, $columns, $item->getTableId(), $viewId);

		foreach ($data as $entry) {
			// Check whether the column of which the value should change is part of the table / view
			$column = $this->getColumnFromColumnsArray($entry['columnId'], $columns);
			if ($column) {
				$item->insertOrUpdateCell($entry);
			} else {
				$this->logger->warning("Column to update row not found, will continue and ignore this.");
			}
		}

		return $this->row2Mapper->update($item, $columns);
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
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (NotFoundError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		if ($viewId) {
			// security
			if (!$this->permissionsService->canDeleteRowsByViewId($viewId)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			try {
				$view = $this->viewMapper->find($viewId);
			} catch (InternalError|DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			$rowIds = $this->mapper->getRowIdsOfView($view, $userId);
			if(!in_array($id, $rowIds)) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		} else {
			// security
			if (!$this->permissionsService->canDeleteRowsByTableId($item->getTableId())) {
				$e = new \Exception('Update row is not allowed.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new PermissionError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		}

		try {
			return $this->row2Mapper->delete($item);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
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
			throw new PermissionError('delete all rows for table id = '.$tableId.' is not allowed.');
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
			throw new PermissionError('no read access for counting to table id = '.$tableId);
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
				return $this->row2Mapper->countRowsForView($view, $userId, $this->columnMapper->findMultiple($view->getColumnsArray()));
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				return 0;
			}
		} else {
			throw new PermissionError('no read access for counting to view id = '.$view->getId());
		}
	}
}
