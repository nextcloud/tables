<?php

namespace OCA\Tables\Service;

use DateTime;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row;
use OCA\Tables\Db\RowMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Service\ColumnTypes\IColumnTypeBusiness;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class RowService extends SuperService {
	private RowMapper $mapper;
	private ColumnMapper $columnMapper;
	private ViewMapper $viewMapper;
	private TableMapper $tableMapper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
		RowMapper $mapper, ColumnMapper $columnMapper, ViewMapper $viewMapper, TableMapper $tableMapper) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
		$this->columnMapper = $columnMapper;
		$this->viewMapper = $viewMapper;
		$this->tableMapper = $tableMapper;
	}

	/**
	 * @param int $tableId
	 * @param string $userId
	 * @param ?int $limit
	 * @param ?int $offset
	 * @return array
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAllByTable(int $tableId, string $userId, ?int $limit = null, ?int $offset = null): array {
		try {
			if ($this->permissionsService->canReadRowsByElementId($tableId, 'table', $userId)) {
				return $this->mapper->findAllByTable($tableId, $limit, $offset);
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
	 * @return array
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 */
	public function findAllByView(int $viewId, string $userId, ?int $limit = null, ?int $offset = null): array {
		try {
			if ($this->permissionsService->canReadRowsByElementId($viewId, 'view', $userId)) {
				return $this->mapper->findAllByView($this->viewMapper->find($viewId), $userId, $limit, $offset);
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
	 * @return Row
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function find(int $id): Row {
		try {
			$row = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canReadRowsByElementId($row->getTableId(), 'table')) {
				throw new PermissionError('PermissionError: can not read row with id '.$id);
			}

			return $row;
		} catch (DoesNotExistException $e) {
			$this->logger->warning($e->getMessage());
			throw new NotFoundError($e->getMessage());
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @param list<array{columnId: int, value: mixed}> $data
	 * @return Row
	 *
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws Exception
	 * @throws InternalError
	 */
	public function create(?int $tableId, ?int $viewId, array $data):Row {
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

		$time = new DateTime();
		$item = new Row();
		$item->setDataArray($data);
		$item->setTableId($viewId ? $view->getTableId() : $tableId);
		$item->setCreatedBy($this->userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditBy($this->userId);
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		return $this->mapper->insert($item);
	}

	/**
	 * @param list<array{columnId: string|int, value: mixed}> $data
	 * @param Column[] $columns
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @return list<array{columnId: int, value: float|int|string}>|null
	 *
	 * @throws InternalError
	 */
	private function cleanupData(array $data, array $columns, ?int $tableId, ?int $viewId): ?array {
		$out = null;
		foreach ($data as $entry) {
			$column = $this->getColumnFromColumnsArray((int) $entry['columnId'], $columns);

			// check if it is allowed to insert a value for the requested column
			if (!$column && $viewId) {
				throw new InternalError('Column with id '.$entry['columnId'].' is not part of view with id '.$viewId);
			} elseif (!$column && $tableId) {
				throw new InternalError('Column with id '.$entry['columnId'].' is not part of table with id '.$tableId);
			}

			// parse given value to respect the column type value format
			$value = $this->parseValueByColumnType($entry['value'], $column);
			$out[] = [
				'columnId' => (int) $entry['columnId'],
				'value' => $value
			];
		}
		return $out;
	}

	/**
	 * @param string $value
	 * @param Column $column
	 * @return string|int|float
	 */
	private function parseValueByColumnType(string $value, Column $column) {
		try {
			$businessClassName = 'OCA\Tables\Service\ColumnTypes\\';
			$businessClassName .= ucfirst($column->getType()).ucfirst($column->getSubtype()).'Business';
			/** @var IColumnTypeBusiness $columnBusiness */
			$columnBusiness = Server::get($businessClassName);
			if(!$columnBusiness->canBeParsed($value, $column)) {
				$this->logger->warning('Value '.$value.' could not be parsed for column '.$column->getTitle());
				return $value;
			}
			/** @noinspection PhpComposerExtensionStubsInspection */
			return json_decode($columnBusiness->parseValue($value, $column));
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type business class not found', ['exception' => $e]);
		}
		return $value;
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
	 * Update multiple cells in a row
	 *
	 * @param int $id
	 * @param int|null $viewId
	 * @param list<array{columnId: string|int, value: mixed}> $data
	 * @param string $userId
	 * @return Row
	 *
	 * @throws InternalError
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @noinspection DuplicatedCode
	 */
	public function updateSet(
		int $id,
		?int $viewId,
		array $data,
		string $userId
	):Row {
		try {
			$item = $this->mapper->find($id);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		if ($viewId) {
			// security
			if (!$this->permissionsService->canUpdateRowsByViewId($viewId)) {
				throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
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

			$rowIds = $this->mapper->getRowIdsOfView($view, $userId);
			if(!in_array($id, $rowIds)) {
				throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
			}

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
				throw new PermissionError('update row id = '.$tableId.' is not allowed.');
			}
			try {
				$columns = $this->columnMapper->findAllByTable($tableId);
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		}

		$data = $this->cleanupData($data, $columns, $item->getTableId(), $viewId);

		$time = new DateTime();
		$oldData = $item->getDataArray();
		foreach ($data as $entry) {
			// Check whether the column of which the value should change is part of the table / view
			$column = $this->getColumnFromColumnsArray($entry['columnId'], $columns);
			if ($column) {
				$oldData = $this->replaceOrAddData($oldData, $entry);
			} else {
				$this->logger->warning("Column to update row not found, will continue and ignore this.");
			}
		}

		$item->setDataArray($oldData);
		$item->setLastEditBy($this->userId);
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			return $this->mapper->update($item);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
	}

	private function replaceOrAddData(array $dataArray, array $newDataObject): array {
		$columnId = (int) $newDataObject['columnId'];
		$value = $newDataObject['value'];

		$columnFound = false;
		foreach ($dataArray as $key => $c) {
			if ($c['columnId'] == $columnId) {
				$dataArray[$key]['value'] = $value;
				$columnFound = true;
				break;
			}
		}
		// if the value was not set, add it
		if (!$columnFound) {
			$dataArray[] = [
				"columnId" => $columnId,
				"value" => $value
			];
		}
		return $dataArray;
	}

	/**
	 * @param int $id
	 * @param int|null $viewId
	 * @param string $userId
	 * @return Row
	 *
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function delete(int $id, ?int $viewId, string $userId): Row {
		try {
			$item = $this->mapper->find($id);

			if ($viewId) {
				// security
				if (!$this->permissionsService->canDeleteRowsByViewId($viewId)) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
				$view = $this->viewMapper->find($viewId);
				$rowIds = $this->mapper->getRowIdsOfView($view, $userId);
				if(!in_array($id, $rowIds)) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
			} else {
				// security
				if (!$this->permissionsService->canDeleteRowsByTableId($item->getTableId())) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
			}

			$this->mapper->delete($item);
			return $item;
		} catch (DoesNotExistException $e) {
			throw new NotFoundError($e->getMessage());
		} catch (MultipleObjectsReturnedException|Exception $e) {
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $tableId
	 * @param null|string $userId
	 * @return int
	 *
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function deleteAllByTable(int $tableId, ?string $userId = null): int {
		// security
		if (!$this->permissionsService->canDeleteRowsByTableId($tableId, $userId)) {
			throw new PermissionError('delete all rows for table id = '.$tableId.' is not allowed.');
		}

		return $this->mapper->deleteAllByTable($tableId);
	}

	/**
	 * @param int $columnId
	 *
	 * @throws PermissionError
	 * @throws Exception
	 */
	public function deleteColumnDataFromRows(int $columnId):void {
		$rows = $this->mapper->findAllWithColumn($columnId);

		// security
		if (count($rows) > 0) {
			if (!$this->permissionsService->canUpdateRowsByTableId($rows[0]->getTableId())) {
				throw new PermissionError('update row id = '.$rows[0]->getId().' within '.__FUNCTION__.' is not allowed.');
			}
		}

		foreach ($rows as $row) {
			/* @var $row Row */
			$data = $row->getDataArray();
			foreach ($data as $key => $col) {
				if ($col['columnId'] == $columnId) {
					unset($data[$key]);
				}
			}
			$row->setDataArray($data);
			$this->mapper->update($row);
		}
	}

	/**
	 * @param int $tableId
	 * @return int
	 *
	 * @throws PermissionError
	 */
	public function getRowsCount(int $tableId): int {
		if ($this->permissionsService->canReadRowsByElementId($tableId, 'table')) {
			return $this->mapper->countRows($tableId);
		} else {
			throw new PermissionError('no read access for counting to table id = '.$tableId);
		}
	}

	/**
	 * @param View $view
	 * @param string $userId
	 * @return int
	 *
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function getViewRowsCount(View $view, string $userId): int {
		if ($this->permissionsService->canReadRowsByElementId($view->getId(), 'view')) {
			return $this->mapper->countRowsForView($view, $userId);
		} else {
			throw new PermissionError('no read access for counting to view id = '.$view->getId());
		}
	}
}
