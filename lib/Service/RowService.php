<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;
use OCA\Tables\Db\Row;
use OCA\Tables\Db\RowMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

class RowService extends SuperService {
	private RowMapper $mapper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
								RowMapper $mapper) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
	}


	/**
	 * @param int $tableId
	 * @param ?int $limit
	 * @param ?int $offset
	 * @return array
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAllByTable(int $tableId, ?int $limit = null, ?int $offset = null): array {
		try {
			if ($this->permissionsService->canReadRowsByTableId($tableId)) {
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
			/** @noinspection PhpUndefinedMethodInspection */
			if (!$this->permissionsService->canReadRowsByTableId($row->getTableId())) {
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
	 * @param int $tableId
	 * @param int $columnId
	 * @param string $data
	 * @return Row
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUndefinedMethodInspection
	 * @noinspection DuplicatedCode
	 */
	public function create(
		int $tableId,
		int $columnId,
		string $data
	):Row {
		// security
		if (!$this->permissionsService->canCreateRowsByTableId($tableId)) {
			throw new PermissionError('create row at the table id = '.$tableId.' is not allowed.');
		}

		$time = new DateTime();
		$item = new Row();
		$d = [];
		$d[] = (object) [
			"columnId" => $columnId,
			"value" => $data
		];
		$item->setDataArray($d);
		$item->setTableId($tableId);
		$item->setCreatedBy($this->userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditBy($this->userId);
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		return $this->mapper->insert($item);
	}

	/**
	 * @param int $tableId
	 * @param array $data
	 * @return Row
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUndefinedMethodInspection
	 * @noinspection DuplicatedCode
	 */
	public function createComplete(
		int $tableId,
		array $data
	):Row {
		// security
		if (!$this->permissionsService->canCreateRowsByTableId($tableId)) {
			throw new PermissionError('create row at the table id = '.$tableId.' is not allowed.');
		}

		$time = new DateTime();
		$item = new Row();
		$item->setDataArray($data);
		$item->setTableId($tableId);
		$item->setCreatedBy($this->userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditBy($this->userId);
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		return $this->mapper->insert($item);
	}

	/**
	 * @noinspection PhpUndefinedMethodInspection
	 * @noinspection DuplicatedCode
	 * @param int $id
	 * @param int $columnId
	 * @param string $data
	 * @return Row
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function update(
		int $id,
		int $columnId,
		string $data
	):Row {
		try {
			$item = $this->find($id);

			// security
			if (!$this->permissionsService->canUpdateRowsByTableId($item->getTableId())) {
				throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
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
	 * @noinspection PhpUndefinedMethodInspection
	 * @noinspection DuplicatedCode
	 * @param int $id
	 * @param array $data
	 * @return Row
	 * @throws InternalError
	 */
	public function updateSet(
		int $id,
		array $data
	):Row {
		try {
			$item = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canUpdateRowsByTableId($item->getTableId())) {
				throw new PermissionError('update row id = '.$item->getId().' is not allowed.');
			}

			$time = new DateTime();
			$d = $item->getDataArray();
			foreach ($data as $dataObject) {
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
		$columnId = intval($newDataObject['columnId']);
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
	 * @return Row
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function delete(int $id): Row {
		try {
			$item = $this->mapper->find($id);

			// security
			/** @noinspection PhpUndefinedMethodInspection */
			if (!$this->permissionsService->canDeleteRowsByTableId($item->getTableId())) {
				throw new PermissionError('delete row id = '.$item->getId().' is not allowed.');
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

	public function getRowsCount(int $tableId): int {
		try {
			if ($this->permissionsService->canReadRowsByTableId($tableId)) {
				return $this->mapper->countRows($tableId);
			} else {
				throw new PermissionError('no read access for counting to table id = '.$tableId);
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}
}
