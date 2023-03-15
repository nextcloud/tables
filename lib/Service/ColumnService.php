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
	private ColumnMapper $mapper;

	private RowService $rowService;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		ColumnMapper $mapper,
		RowService $rowService
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
		$this->rowService = $rowService;
	}


	/**
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAllByTable(int $tableId, ?string $userId = null): array {
		try {
			if ($this->permissionsService->canReadColumnsByTableId($tableId, $userId)) {
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
	public function find(int $id): Column {
		try {
			$column = $this->mapper->find($id);

			// security
			/** @noinspection PhpUndefinedMethodInspection */
			if (!$this->permissionsService->canReadColumnsByTableId($column->getTableId())) {
				throw new PermissionError('PermissionError: can not read column with id '.$id);
			}

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
	 * @param int $tableId
	 * @param string $title
	 * @param string $userId
	 * @param string $type
	 * @param string $subtype
	 * @param string $numberPrefix
	 * @param string $numberSuffix
	 * @param bool $mandatory
	 * @param string $description
	 * @param string $textDefault
	 * @param string $textAllowedPattern
	 * @param int $textMaxLength
	 * @param float|null $numberDefault
	 * @param float|null $numberMin
	 * @param float|null $numberMax
	 * @param int|null $numberDecimals
	 * @param string $selectionOptions
	 * @param string $selectionDefault
	 * @param int $orderWeight
	 * @param string $datetimeDefault
	 * @return Column
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
		?float $numberDefault = null,
		?float $numberMin = null,
		?float $numberMax = null,
		?int $numberDecimals = null,
		string $selectionOptions = '',
		string $selectionDefault = '',
		int $orderWeight = 0,
		string $datetimeDefault = ''
	):Column {
		// security
		if (!$this->permissionsService->canCreateColumnsByTableId($tableId)) {
			throw new PermissionError('create column at the table id = '.$tableId.' is not allowed.');
		}

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
	 * @param int $id
	 * @param int $tableId
	 * @param string $userId
	 * @param string $title
	 * @param string $type
	 * @param string $subtype
	 * @param string $numberPrefix
	 * @param string $numberSuffix
	 * @param bool $mandatory
	 * @param string $description
	 * @param string $textDefault
	 * @param string $textAllowedPattern
	 * @param int|null $textMaxLength
	 * @param float|null $numberDefault
	 * @param float|null $numberMin
	 * @param float|null $numberMax
	 * @param int|null $numberDecimals
	 * @param string $selectionOptions
	 * @param string $selectionDefault
	 * @param int $orderWeight
	 * @param string $datetimeDefault
	 * @return Column
	 * @throws InternalError
	 */
	public function update(
		int $id,
		int $tableId,
		string $userId,
		string $title,
		string $type,
		string $subtype,
		string $numberPrefix,
		string $numberSuffix,
		bool $mandatory,
		string $description,
		string $textDefault,
		string $textAllowedPattern,
		?int $textMaxLength,
		?float $numberDefault = null,
		?float $numberMin = null,
		?float $numberMax = null,
		?int $numberDecimals = null,
		string $selectionOptions = '',
		string $selectionDefault = '',
		int $orderWeight = 0,
		string $datetimeDefault = ''
	):Column {
		try {
			// security
			if (!$this->permissionsService->canUpdateColumnsByTableId($tableId)) {
				throw new PermissionError('update column id = '.$id.' is not allowed.');
			}


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
	 * @param int $id
	 * @param bool $skipRowCleanup
	 * @param null|string $userId
	 * @return Column
	 * @throws InternalError
	 */
	public function delete(int $id, bool $skipRowCleanup = false, ?string $userId = null): Column {
		try {
			$item = $this->mapper->find($id);

			// security
			/** @noinspection PhpUndefinedMethodInspection */
			if (!$this->permissionsService->canDeleteColumnsByTableId($item->getTableId(), $userId)) {
				throw new PermissionError('delete column id = '.$id.' is not allowed.');
			}

			if (!$skipRowCleanup) {
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
