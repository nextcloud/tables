<?php

namespace OCA\Tables\Service;

use Exception;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row;
use OCA\Tables\Db\RowMapper;
use OCA\Tables\Db\Table;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class RowService {

	/** @var RowMapper */
	private $mapper;

	public function __construct(RowMapper $mapper) {
		$this->mapper = $mapper;
	}

    /**
     * @throws Exception
     */
    public function findAllByTable(string $userId, int $tableId): array {
		return $this->mapper->findAllByTable($tableId, $userId);
	}

    /**
     * @throws TableNotFound
     * @throws Exception
     */
    private function handleException(Exception $e): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new TableNotFound($e->getMessage());
		} else {
			throw $e;
		}
	}

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     * @throws \OCP\DB\Exception
     */
    public function find($id, $userId) {
        return $this->mapper->find($id, $userId);
    }

    /**
     * @throws \OCP\DB\Exception
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function create(
        int $tableId,
        int $columnId,
        string $userId,
        string $data
    ) {
        $time = new \DateTime();
        $item = new Row();
        $d = [];
        $d[] = (object) [
            "columnId" => $columnId,
            "value" => $data
        ];
        $item->setDataArray($d);
        $item->setTableId($tableId);
        $item->setCreatedBy($userId);
        $item->setCreatedAt($time->format('Y-m-d H:i:s'));
        $item->setLastEditBy($userId);
        $item->setLastEditAt($time->format('Y-m-d H:i:s'));
		return $this->mapper->insert($item);
	}

    /**
     * @throws \OCP\DB\Exception
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function createComplete(
        int $tableId,
        string $userId,
        Array $data
    ) {
        $time = new \DateTime();
        $item = new Row();
        $item->setDataArray($data);
        $item->setTableId($tableId);
        $item->setCreatedBy($userId);
        $item->setCreatedAt($time->format('Y-m-d H:i:s'));
        $item->setLastEditBy($userId);
        $item->setLastEditAt($time->format('Y-m-d H:i:s'));
        return $this->mapper->insert($item);
    }

    /** @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function update(
        int $id,
        int $columnId,
        string $userId,
        string $data
    ) {
		try {
            $time = new \DateTime();
            $item = $this->mapper->find($id);
            $d = $item->getDataArray();
            $columnFound = false;
            foreach ($d as $key => $c) {
                if($c['columnId'] == $columnId) {
                    $d[$key]['value'] = $data;
                    $columnFound = true;
                    break;
                }
            }
            // if the value was not set, add it
            if(!$columnFound) {
                $d[] = (object) [
                        "columnId" => $columnId,
                        "value" => $data
                    ];
            }
            $item->setDataArray($d);
            $item->setLastEditBy($userId);
            $item->setLastEditAt($time->format('Y-m-d H:i:s'));
			return $this->mapper->update($item);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function delete($id, $userId) {
		try {
            $item = $this->mapper->find($id, $userId);
			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
			$this->handleException($e);
        }
    }

    /**
     * @throws \OCP\DB\Exception
     */
    public function deleteAllByTable(int $tableId): int
    {
        return $this->mapper->deleteAllByTable($tableId);
    }

    /**
     * @throws \OCP\DB\Exception
     */
    public function deleteColumnDataFromRows(int $columnId) {
        $rows = $this->mapper->findAllWithColumn($columnId);
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
