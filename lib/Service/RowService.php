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

	/** @var RowMapper */
	private $mapper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, $userId,
                                RowMapper $mapper) {
        parent::__construct($logger, $userId, $permissionsService);
        $this->mapper = $mapper;
	}


    /**
     * @throws InternalError
     * @throws PermissionError
     */
    public function findAllByTable(int $tableId): array {
        try {
            if($this->permissionsService->canReadRows($tableId)) {
                return $this->mapper->findAllByTable($tableId);
            } else {
                throw new PermissionError('no read access to table id = '.$tableId);
            }
        } catch (\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }


    /**
     * @throws NotFoundError
     * @throws InternalError
     * @throws PermissionError
     */
    public function find($id) {
        try {
            $row = $this->mapper->find($id);

            // security
            /** @noinspection PhpUndefinedMethodInspection */
            if(!$this->permissionsService->canReadRows($row->getTableId()))
                throw new PermissionError('PermissionError: can not read row with id '.$id);

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
     * @throws \OCP\DB\Exception
     * @throws PermissionError
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function create(
        int $tableId,
        int $columnId,
        string $data
    ) {

        // security
        if(!$this->permissionsService->canCreateRows($tableId))
            throw new PermissionError('create row at the table id = '.$tableId.' is not allowed.');

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
     * @throws \OCP\DB\Exception
     * @throws PermissionError
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function createComplete(
        int $tableId,
        Array $data
    ) {

        // security
        if(!$this->permissionsService->canCreateRows($tableId))
            throw new PermissionError('create row at the table id = '.$tableId.' is not allowed.');

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
     * @throws InternalError
     * @throws NotFoundError|PermissionError
     */
    public function update(
        int $id,
        int $columnId,
        string $data
    ) {
        try {

            $item = $this->find($id);

            // security
            if(!$this->permissionsService->canUpdateRows($item->getTableId()))
                throw new PermissionError('update row id = '.$item->getId().' is not allowed.');

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
     * @throws InternalError
     */
    public function updateSet(
        int $id,
        array $data
    ) {
        try {
            $item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canUpdateRows($item->getTableId()))
                throw new PermissionError('update row id = '.$item->getId().' is not allowed.');

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

    private function replaceOrAddData($dataArray, $newDataObject): array
    {
        $columnId = intval($newDataObject['columnId']);
        $value = $newDataObject['value'];

        $columnFound = false;
        foreach ($dataArray as $key => $c) {
            if($c['columnId'] == $columnId) {
                $dataArray[$key]['value'] = $value;
                $columnFound = true;
                break;
            }
        }
        // if the value was not set, add it
        if(!$columnFound) {
            $dataArray[] = [
                "columnId" => $columnId,
                "value" => $value
            ];
        }
        return $dataArray;
    }

    /**
     * @throws PermissionError
     * @throws NotFoundError
     * @throws InternalError
     */
    public function delete($id) {
        try {
            $item = $this->mapper->find($id);

            // security
            /** @noinspection PhpUndefinedMethodInspection */
            if(!$this->permissionsService->canDeleteRows($item->getTableId()))
                throw new PermissionError('delete row id = '.$item->getId().' is not allowed.');

            $this->mapper->delete($item);
            return $item;
        } catch (DoesNotExistException $e) {
            throw new NotFoundError($e->getMessage());
        } catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
            throw new InternalError($e->getMessage());
        }
    }

    /**
     * @throws \OCP\DB\Exception
     * @throws PermissionError
     */
    public function deleteAllByTable(int $tableId): int
    {
        // security
        if(!$this->permissionsService->canDeleteRows($tableId))
            throw new PermissionError('delete all rows for table id = '.$tableId.' is not allowed.');

        return $this->mapper->deleteAllByTable($tableId);
    }

    /**
     * @throws \OCP\DB\Exception
     * @throws PermissionError
     */
    public function deleteColumnDataFromRows(int $columnId) {

        $rows = $this->mapper->findAllWithColumn($columnId);

        // security
        if(count($rows) > 0) {
            if(!$this->permissionsService->canUpdateRows($rows[0]->getTableId()))
                throw new PermissionError('update row id = '.$rows[0]->getId().' within '.__FUNCTION__.' is not allowed.');
        }

        foreach ($rows as $row) {
            /* @var $row Row */
            $data = $row->getDataArray();
            foreach ($data as $key => $col) {
                if($col['columnId'] == $columnId) {
                    unset($data[$key]);
                }
            }
            $row->setDataArray($data);
            $this->mapper->update($row);
        }
    }
}
