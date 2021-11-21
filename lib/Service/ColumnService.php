<?php

namespace OCA\Tables\Service;

use Exception;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;

class ColumnService {

	/** @var ColumnMapper */
	private $mapper;

	public function __construct(ColumnMapper $mapper) {
		$this->mapper = $mapper;
	}

    /**
     * @throws Exception
     */
    public function findAllByTable(string $userId, int $tableId): array {
		return $this->mapper->findAllByTable($userId, $tableId);
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
        string $title,
        string $userId,
        string $type,
        string $prefix,
        string $suffix,
        bool $mandatory,
        string $description,
        string $textDefault,
        string $textAllowedPattern,
        int $textMaxLength,
        bool $textMultiline,
        float $numberDefault,
        float $numberMin,
        float $numberMax,
        int $numberDecimals
    ) {
        $time = new \DateTime();
		$item = new Column();
        $item->setTitle($title);
        $item->setTableId($tableId);
        $item->setType($type);
        $item->setPrefix($prefix);
        $item->setSuffix($suffix);
        $item->setMandatory($mandatory);
        $item->setDescription($description);
        $item->setTextDefault($textDefault);
        $item->setTextAllowedPattern($textAllowedPattern);
        $item->setTextMaxLength($textMaxLength);
        $item->setTextMultiline($textMultiline);
        $item->setNumberDefault($numberDefault);
        $item->setNumberMin($numberMin);
        $item->setNumberMax($numberMax);
        $item->setNumberDecimals($numberDecimals);
        $item->setCreatedBy($userId);
        $item->setLastEditBy($userId);
        $item->setCreatedAt($time->format('Y-m-d H:i:s'));
        $item->setLastEditAt($time->format('Y-m-d H:i:s'));
		return $this->mapper->insert($item);
	}

    /** @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function update(
        $id,
        $tableId,
        $userId,
        $title,
        $type,
        $prefix,
        $suffix,
        $mandatory,
        $description,
        $textDefault,
        $textAllowedPattern,
        $textMaxLength,
        $textMultiline,
        $numberDefault,
        $numberMin,
        $numberMax,
        $numberDecimals
    ) {
		try {
            $time = new \DateTime();
            $item = new Column();
            $item->setId($id);
            $item->setTitle($title);
            $item->setTableId($tableId);
            $item->setUserId($userId);
            $item->setType($type);
            $item->setPrefix($prefix);
            $item->setSuffix($suffix);
            $item->setMandatory($mandatory);
            $item->setDescription($description);
            $item->setTextDefault($textDefault);
            $item->setTextAllowedPattern($textAllowedPattern);
            $item->setTextMaxLength($textMaxLength);
            $item->setTextMultiline($textMultiline);
            $item->setNumberDefault($numberDefault);
            $item->setNumberMin($numberMin);
            $item->setNumberMax($numberMax);
            $item->setNumberDecimals($numberDecimals);
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
}
