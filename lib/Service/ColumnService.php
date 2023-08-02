<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class ColumnService extends SuperService {
	private ColumnMapper $mapper;

	private TableMapper $tableMapper;

	private ViewService $viewService;

	private RowService $rowService;

	private IL10N $l;


	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		ColumnMapper $mapper,
		TableMapper $tableMapper,
		ViewService $viewService,
		RowService $rowService,
		IL10N $l
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
		$this->tableMapper = $tableMapper;
		$this->viewService = $viewService;
		$this->rowService = $rowService;
		$this->l = $l;
	}


	/**
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAllByTable(int $tableId, ?int $viewId = null, ?string $userId = null): array {
		try {
			if ($this->permissionsService->canReadColumnsByTableId($tableId, $userId) || ($viewId != null && $this->permissionsService->canReadColumnsByViewId($viewId, $userId))) {
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
	 * @param int $viewId
	 * @return array
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function findAllByView(int $viewId): array {
		try {
			// No need to check for columns outside the view since they cannot be addressed
			$view = $this->viewService->find($viewId, true);
			$viewColumnIds = $view->getColumnsArray();
			$viewColumns = [];
			foreach ($viewColumnIds as $viewColumnId) {
				if ($viewColumnId < 0) {
					continue;
				}
				try {
					$viewColumns[] = $this->mapper->find($viewColumnId);
				} catch (DoesNotExistException $e) {
					$this->logger->warning($e->getMessage());
					throw new NotFoundError($e->getMessage());
				} catch (MultipleObjectsReturnedException $e) {
					$this->logger->error($e->getMessage());
					throw new InternalError($e->getMessage());
				}
			}
			return $viewColumns;
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
	public function find(int $id, string $userId = null): Column {
		try {
			$column = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canReadColumnsByTableId($column->getTableId(), $userId)) {
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
	 * @noinspection DuplicatedCode
	 *
	 * @param string|null $userId
	 * @param int $viewId
	 * @param string $type
	 * @param string|null $subtype
	 * @param string $title
	 * @param bool $mandatory
	 * @param string|null $description
	 * @param string|null $textDefault
	 * @param string|null $textAllowedPattern
	 * @param int|null $textMaxLength
	 * @param string|null $numberPrefix
	 * @param string|null $numberSuffix
	 * @param float|null $numberDefault
	 * @param float|null $numberMin
	 * @param float|null $numberMax
	 * @param int|null $numberDecimals
	 * @param string|null $selectionOptions
	 * @param string|null $selectionDefault
	 * @param string|null $datetimeDefault
	 * @param array|null $selectedViewIds
	 * @return Column
	 *
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
	 */
	public function create(
		?string $userId,
		?int $tableId,
		?int $viewId,
		string $type,
		?string $subtype,
		string $title,
		bool $mandatory,
		?string $description,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $selectionOptions,
		?string $selectionDefault,

		?string $datetimeDefault,
		?array $selectedViewIds
	):Column {
		// security
		if ($viewId) {
			$view = $this->viewService->find($viewId);
			$table = $this->tableMapper->find($view->getTableId());
		} else if ($tableId) {
			$table = $this->tableMapper->find($tableId);
		} else {
			throw new InternalError('Cannot update row without table or view in context');
		}

		if (!$this->permissionsService->canCreateColumns($table)) {
			throw new PermissionError('create column at the table id = '.$table->getId().' is not allowed.');
		}

		$time = new DateTime();
		$item = new Column();
		$item->setTitle($title);
		$item->setTableId($table->getId());
		$item->setType($type);
		$item->setSubtype($subtype !== null ? $subtype: '');
		$item->setMandatory($mandatory);
		$item->setDescription($description);
		$item->setTextDefault($textDefault);
		$item->setTextAllowedPattern($textAllowedPattern);
		$item->setTextMaxLength($textMaxLength);
		$item->setNumberDefault($numberDefault);
		$item->setNumberMin($numberMin);
		$item->setNumberMax($numberMax);
		$item->setNumberDecimals($numberDecimals);
		$item->setNumberPrefix($numberPrefix !== null ? $numberPrefix: '');
		$item->setNumberSuffix($numberSuffix !== null ? $numberSuffix: '');
		$item->setCreatedBy($userId);
		$item->setLastEditBy($userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		$item->setSelectionOptions($selectionOptions);
		$item->setSelectionDefault($selectionDefault);
		$item->setDatetimeDefault($datetimeDefault);
		try {
			$entity = $this->mapper->insert($item);
			if($viewId) {
				// Add columns to view(s)
				$this->viewService->update($view->getId(), ['columns' => json_encode(array_merge($view->getColumnsArray(), [$entity->getId()]))], $userId, true);
			}
			foreach ($selectedViewIds as $viewId) {
				$view = $this->viewService->find($viewId);
				$this->viewService->update($viewId, ['columns' => json_encode(array_merge($view->getColumnsArray(), [$entity->getId()]))], $userId, true);
			}
			return $entity;
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @noinspection DuplicatedCode
	 * @param int $columnId
	 * @param int|null $tableId
	 * @param string|null $userId
	 * @param string|null $type
	 * @param string|null $subtype
	 * @param string|null $title
	 * @param bool $mandatory
	 * @param string|null $description
	 * @param string|null $textDefault
	 * @param string|null $textAllowedPattern
	 * @param int|null $textMaxLength
	 * @param string|null $numberPrefix
	 * @param string|null $numberSuffix
	 * @param float|null $numberDefault
	 * @param float|null $numberMin
	 * @param float|null $numberMax
	 * @param int|null $numberDecimals
	 * @param string|null $selectionOptions
	 * @param string|null $selectionDefault
	 * @param string|null $datetimeDefault
	 * @return Column
	 * @throws InternalError
	 */
	public function update(
		int $columnId,
		?int $tableId,
		?string $userId,
		?string $type,
		?string $subtype,
		?string $title,
		?bool $mandatory,
		?string $description,

		?string $textDefault,
		?string $textAllowedPattern,
		?int $textMaxLength,

		?string $numberPrefix,
		?string $numberSuffix,
		?float $numberDefault,
		?float $numberMin,
		?float $numberMax,
		?int $numberDecimals,

		?string $selectionOptions,
		?string $selectionDefault,
		?string $datetimeDefault
	):Column {
		try {
			$item = $this->mapper->find($columnId);

			// security
			if (!$this->permissionsService->canUpdateColumnsByTableId($item->getTableId())) {
				throw new PermissionError('update column id = '.$columnId.' is not allowed.');
			}

			if ($title !== null) {
				$item->setTitle($title);
			}
			if ($tableId !== null) {
				$item->setTableId($tableId);
			}
			if ($type !== null) {
				$item->setType($type);
			}
			if ($subtype !== null) {
				$item->setSubtype($subtype);
			}
			if ($numberPrefix !== null) {
				$item->setNumberPrefix($numberPrefix);
			}
			if ($numberSuffix !== null) {
				$item->setNumberSuffix($numberSuffix);
			}
			if ($mandatory !== null) {
				$item->setMandatory($mandatory);
			}
			if ($description !== null) {
				$item->setDescription($description);
			}
			if ($textDefault !== null) {
				$item->setTextDefault($textDefault);
			}
			if ($textAllowedPattern !== null) {
				$item->setTextAllowedPattern($textAllowedPattern);
			}
			if ($textMaxLength !== null) {
				$item->setTextMaxLength($textMaxLength);
			}
			if ($numberDefault !== null) {
				$item->setNumberDefault($numberDefault);
			}
			if ($numberMin !== null) {
				$item->setNumberMin($numberMin);
			}
			if ($numberMax !== null) {
				$item->setNumberMax($numberMax);
			}
			if ($numberDecimals !== null) {
				$item->setNumberDecimals($numberDecimals);
			}
			if ($selectionOptions !== null) {
				$item->setSelectionOptions($selectionOptions);
			}
			if ($selectionDefault !== null) {
				$item->setSelectionDefault($selectionDefault);
			}
			if ($datetimeDefault !== null) {
				$item->setDatetimeDefault($datetimeDefault);
			}

			$time = new DateTime();
			$item->setLastEditAt($time->format('Y-m-d H:i:s'));
			$item->setLastEditBy($userId);
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
			if (!$this->permissionsService->canDeleteColumnsByTableId($item->getTableId(), $userId)) {
				throw new PermissionError('delete column id = '.$id.' is not allowed.');
			}

			if (!$skipRowCleanup) {
				$this->rowService->deleteColumnDataFromRows($id);
				$table = $this->tableMapper->find($item->getTableId());
				$this->viewService->deleteColumnDataFromViews($id, $table);
			}

			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $tableId
	 * @param int $viewId
	 * @param array $titles example ['Test column 1', 'And so on', '3rd column title']
	 * @param string|null $userId
	 * @param bool $createUnknownColumns
	 * @param int $countCreatedColumns
	 * @return array with column object or null for given columns
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
	 */
	public function findOrCreateColumnsByTitleForTableAsArray(int $viewId, array $titles, ?string $userId, bool $createUnknownColumns, int &$countCreatedColumns, int &$countMatchingColumns): array {
		$result = [];

		if($userId === null) {
			$userId = $this->userId;
		}
		$allColumns = $this->findAllByView($viewId);
		$i = -1;
		foreach ($titles as $title) {
			$i++;
			foreach ($allColumns as $column) {
				if($column->getTitle() === $title) {
					$result[$i] = $column;
					$countMatchingColumns++;
					continue 2;
				}
				$result[$i] = '';
			}
			// if there are no columns at all
			if(!isset($result[$i])) {
				$result[$i] = '';
			}
			// if column was not found
			if($result[$i] === '' && $createUnknownColumns) {
				$description = $this->l->t('This column was automatically created by the import service.');
				$result[$i] = $this->create($userId, null, $viewId, 'text', 'line', $title, false, $description, null, null, null, null, null, null, null, null, null, null, null, null, []);
				$countCreatedColumns++;
			}
		}
		return $result;
	}

	/**
	 * @param int $tableId
	 * @return int
	 * @throws PermissionError
	 */
	public function getColumnsCount(int $tableId): int {
		if ($this->permissionsService->canManageTableById($tableId)) {
			return $this->mapper->countColumns($tableId);
		} else {
			throw new PermissionError('no read access for counting to table id = '.$tableId);
		}
	}
}
