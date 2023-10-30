<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row;
use OCA\Tables\Db\RowMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
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
		} catch (\OCP\DB\Exception $e) {
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
		} catch (\OCP\DB\Exception $e) {
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
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $viewId
	 * @param array $data
	 * @return Row
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
	 * @noinspection DuplicatedCode
	 */
	public function create(
		?int $tableId,
		?int $viewId,
		array $data
	):Row {
		if ($viewId) {

			$view = $this->viewMapper->find($viewId);
			// security
			if (!$this->permissionsService->canCreateRows($view)) {
				throw new PermissionError('create row at the view id = '.$viewId.' is not allowed.');
			}

			$columns = $view->getColumnsArray();
		} elseif ($tableId) {
			$table = $this->tableMapper->find($tableId);
			// security
			if (!$this->permissionsService->canCreateRows($table, 'table')) {
				throw new PermissionError('create row at the table id = '.$tableId.' is not allowed.');
			}
			$columns = $this->columnMapper->findAllIdsByTable($tableId);
		} else {
			throw new InternalError('Cannot create row without table or view in context');
		}

		foreach ($data as $entry) {
			if (!in_array($entry['columnId'], $columns)) {
				throw new InternalError('Column with id '.$entry['columnId'].' is not part of view with id '.$viewId);
			}
		}

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
	 * @noinspection DuplicatedCode
	 * @param int $id
	 * @param int $viewId
	 * @param int $columnId
	 * @param string $data
	 * @param string $userId
	 * @return Row
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function update(
		int $id,
		?int $tableId,
		?int $viewId,
		int $columnId,
		string $data,
		string $userId
	):Row {
		try {
			$item = $this->find($id);

			if ($viewId) {
				// security
				if (!$this->permissionsService->canUpdateRowsByViewId($viewId)) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
				$view = $this->viewMapper->find($viewId);
				$rowIds = $this->mapper->getRowIdsOfView($view, $userId);
				if(!in_array($id, $rowIds)) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
			} elseif ($tableId) {
				// security
				if (!$this->permissionsService->canUpdateRowsByTableId($tableId)) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
			} else {
				throw new InternalError('Cannot update row without table or view in context');
			}

			$time = new DateTime();
			$d = $item->getDataArray();
			$columnFound = false;
			foreach ($d as $key => $c) {
				if ($c['columnId'] == $columnId) {
					$d[$key]['value'] = $data;
					$columnFound = true;
					break;
				}
			}
			// if the value was not set, add it
			if (!$columnFound) {
				$d[] = [
					"columnId" => $columnId,
					"value" => $data
				];
			}
			$item->setDataArray($d);
			$item->setLastEditBy($this->userId);
			$item->setLastEditAt($time->format('Y-m-d H:i:s'));
			return $this->mapper->update($item);
		} catch (InternalError $e) {
			throw new InternalError($e->getMessage());
		} catch (NotFoundError|\OCP\DB\Exception $e) {
			throw new NotFoundError($e->getMessage());
		}
	}

	/**
	 * @noinspection DuplicatedCode
	 * @param int $id
	 * @param int $viewId
	 * @param array $data
	 * @param string $userId
	 * @return Row
	 * @throws InternalError
	 */
	public function updateSet(
		int $id,
		?int $viewId,
		array $data,
		string $userId
	):Row {
		try {
			$item = $this->mapper->find($id);

			if ($viewId) {
				// security
				if (!$this->permissionsService->canUpdateRowsByViewId($viewId)) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
				$view = $this->viewMapper->find($viewId);
				$columnIds = $view->getColumnsArray();
				$rowIds = $this->mapper->getRowIdsOfView($view, $userId);
				if(!in_array($id, $rowIds)) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
			} else {
				// security
				if (!$this->permissionsService->canUpdateRowsByTableId($item->getTableId())) {
					throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
				}
				$columnIds = $this->columnMapper->findAllIdsByTable($item->getTableId());
			}

			$time = new DateTime();
			$d = $item->getDataArray();
			foreach ($data as $dataObject) {
				// Check whether the column of which the value should change is part of the table / view
				if (!in_array($dataObject['columnId'], $columnIds)) {
					continue;
				}
				$d = $this->replaceOrAddData($d, $dataObject);
			}
			$item->setDataArray($d);
			$item->setLastEditBy($this->userId);
			$item->setLastEditAt($time->format('Y-m-d H:i:s'));
			return $this->mapper->update($item);
		} catch (Exception $e) {
			throw new InternalError($e->getMessage());
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
	 * @param int $viewId
	 * @param string $userId
	 * @return Row
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
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $tableId
	 * @param null|string $userId
	 * @return int
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
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
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
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
