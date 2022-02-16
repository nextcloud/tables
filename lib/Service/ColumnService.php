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

    /** @var RowService */
    private $rowService;

	public function __construct(ColumnMapper $mapper, RowService $rowService) {
		$this->mapper = $mapper;
        $this->rowService = $rowService;
	}

    /**
     * @throws Exception
     */
    public function findAllByTable(string $userId, int $tableId): array {
		return $this->mapper->findAllByTable($tableId);
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
        return $this->mapper->find($id);
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
        string $subtype,
        string $numberPrefix,
        string $numberSuffix,
        bool $mandatory,
        string $description,
        string $textDefault,
        string $textAllowedPattern,
        int $textMaxLength,
        float $numberDefault = null,
        float $numberMin = null,
        float $numberMax = null,
        int $numberDecimals = null,
        string $selectionOptions,
        string $selectionDefault,
        int $orderWeight = 0
    ) {
        $time = new \DateTime();
		$item = new Column();
        $item->setTitle($title);
        $item->setTableId($tableId);
        $item->setType($type);
        $item->setSubtype($subtype);
        $item->setNumberPrefix($numberPrefix);
        $item->setNumberSuffix($numberSuffix);
        $item->setMandatory($mandatory);
        $item->setDescription($description);
        $item->setTextDefault($textDefault);
        $item->setTextAllowedPattern($textAllowedPattern);
        $item->setTextMaxLength($textMaxLength);
        $item->setNumberDefault($numberDefault);
        $item->setNumberMin($numberMin);
        $item->setNumberMax($numberMax);
        $item->setNumberDecimals($numberDecimals);
        $item->setCreatedBy($userId);
        $item->setLastEditBy($userId);
        $item->setCreatedAt($time->format('Y-m-d H:i:s'));
        $item->setLastEditAt($time->format('Y-m-d H:i:s'));
        $item->setSelectionOptions($selectionOptions);
        $item->setSelectionDefault($selectionDefault);
        $item->setOrderWeight($orderWeight);
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
        $subtype,
        $numberPrefix,
        $numberSuffix,
        $mandatory,
        $description,
        $textDefault,
        $textAllowedPattern,
        $textMaxLength,
        $numberDefault = null,
        $numberMin = null,
        $numberMax = null,
        $numberDecimals = null,
        $selectionOptions,
        $selectionDefault,
        $orderWeight = 0
    ) {
		try {
            $time = new \DateTime();
            $item = new Column();
            $item->setId($id);
            $item->setTitle($title);
            $item->setTableId($tableId);
            $item->setType($type);
            $item->setSubtype($subtype);
            $item->setNumberPrefix($numberPrefix);
            $item->setNumberSuffix($numberSuffix);
            $item->setMandatory($mandatory);
            $item->setDescription($description);
            $item->setTextDefault($textDefault);
            $item->setTextAllowedPattern($textAllowedPattern);
            $item->setTextMaxLength($textMaxLength);
            $item->setNumberDefault($numberDefault);
            $item->setNumberMin($numberMin);
            $item->setNumberMax($numberMax);
            $item->setNumberDecimals($numberDecimals);
            $item->setLastEditBy($userId);
            $item->setLastEditAt($time->format('Y-m-d H:i:s'));
            $item->setSelectionOptions($selectionOptions);
            $item->setSelectionDefault($selectionDefault);
            $item->setOrderWeight($orderWeight);
			return $this->mapper->update($item);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function delete($id, $userId, bool $skipRowCleanup = false) {
		try {
            if(!$skipRowCleanup) {
                $this->rowService->deleteColumnDataFromRows($id);
            }
            $item = $this->mapper->find($id, $userId);
			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
			$this->handleException($e);
        }
    }
}
