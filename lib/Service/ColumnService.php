<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

class ColumnService extends SuperService {

	/** @var ColumnMapper */
	private $mapper;

    /** @var RowService */
    private $rowService;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, $userId,
                                ColumnMapper $mapper, RowService $rowService) {
        parent::__construct($logger, $userId, $permissionsService);
        $this->mapper = $mapper;
        $this->rowService = $rowService;
	}


    /**
     * @throws InternalError
     * @throws PermissionError
     */
    public function findAllByTable(int $tableId): array {
        try {
            if($this->permissionsService->canReadColumns($tableId)) {
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
            $column = $this->mapper->find($id);

            // security
            /** @noinspection PhpUndefinedMethodInspection */
            if(!$this->permissionsService->canReadColumns($column->getTableId()))
                throw new PermissionError('PermissionError: can not read column with id '.$id);

            return $column;
        } catch (DoesNotExistException $e) {
            $this->logger->warning($e->getMessage());
            throw new NotFoundError($e->getMessage());
        } catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     * @throws InternalError
     * @throws PermissionError
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
        string $selectionOptions = '',
        string $selectionDefault = '',
        int $orderWeight = 0,
        string $datetimeDefault = ''
    ) {
        // security
        if(!$this->permissionsService->canCreateColumns($tableId))
            throw new PermissionError('create column at the table id = '.$tableId.' is not allowed.');

        $time = new DateTime();
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
        $item->setDatetimeDefault($datetimeDefault);
        try {
            return $this->mapper->insert($item);
        } catch (\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     * @throws InternalError
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
        $selectionOptions = '',
        $selectionDefault = '',
        $orderWeight = 0,
        $datetimeDefault = ''
    ) {
		try {

            // security
            if(!$this->permissionsService->canUpdateColumns($tableId))
                throw new PermissionError('update column id = '.$id.' is not allowed.');


            $time = new DateTime();
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
            $item->setDatetimeDefault($datetimeDefault);
			return $this->mapper->update($item);
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
		}
	}

    /**
     * @throws InternalError
     */
    public function delete($id, bool $skipRowCleanup = false, $userId = null) {
		try {
            $item = $this->mapper->find($id);

            // security
            /** @noinspection PhpUndefinedMethodInspection */
            if(!$this->permissionsService->canDeleteColumns($item->getTableId(), $userId))
                throw new PermissionError('delete column id = '.$id.' is not allowed.');

            if(!$skipRowCleanup) {
                $this->rowService->deleteColumnDataFromRows($id);
            }

			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }
}
