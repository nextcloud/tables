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
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesColumn from ResponseDefinitions
 */
class ColumnService extends SuperService {
	private ColumnMapper $mapper;

	private TableMapper $tableMapper;

	private ViewService $viewService;

	private RowService $rowService;

	private IL10N $l;

	private UserHelper $userHelper;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		ColumnMapper $mapper,
		TableMapper $tableMapper,
		ViewService $viewService,
		RowService $rowService,
		IL10N $l,
		UserHelper $userHelper
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
		$this->tableMapper = $tableMapper;
		$this->viewService = $viewService;
		$this->rowService = $rowService;
		$this->l = $l;
		$this->userHelper = $userHelper;
	}


	/**
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAllByTable(int $tableId, ?int $viewId = null, ?string $userId = null): array {
		if ($this->permissionsService->canReadColumnsByTableId($tableId, $userId) || ($viewId != null && $this->permissionsService->canReadColumnsByViewId($viewId, $userId))) {
			try {
				return $this->enhanceColumns($this->mapper->findAllByTable($tableId));
			} catch (\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		} else {
			throw new PermissionError('no read access to table id = '.$tableId);
		}
	}

	/**
	 * @param int $viewId
	 * @param string|null $userId
	 * @return array
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws InternalError
	 */
	public function findAllByView(int $viewId, ?string $userId = null): array {
		// No need to check for columns outside the view since they cannot be addressed
		try {
			$view = $this->viewService->find($viewId, true, $userId);
		} catch (InternalError|MultipleObjectsReturnedException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new PermissionError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		$viewColumnIds = $view->getColumnsArray();
		$viewColumns = [];
		foreach ($viewColumnIds as $viewColumnId) {
			if ($viewColumnId < 0) {
				continue;
			}
			try {
				$viewColumns[] = $this->mapper->find($viewColumnId);
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		}
		return $this->enhanceColumns($viewColumns);
	}

	/**
	 * @param Column[] $columns
	 * @return TablesColumn[]
	 */
	public function formatColumns(array $columns): array {
		return array_map(fn (Column $item) => $item->jsonSerialize(), $columns);
	}

	/**
	 * @throws NotFoundError
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function find(int $id, string $userId = null): Column {
		try {
			/** @var Column $column */
			$column = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canReadColumnsByTableId($column->getTableId(), $userId)) {
				throw new PermissionError('PermissionError: can not read column with id '.$id);
			}

			return $this->enhanceColumn($column);
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
	 * @param int|null $tableId
	 * @param int|null $viewId
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
	 * @param string|null $usergroupDefault
	 * @param bool|null $usergroupMultipleItems
	 * @param bool|null $usergroupSelectUsers
	 * @param bool|null $usergroupSelectGroups
	 * @param bool|null $showUserStatus
	 * @param array $selectedViewIds
	 * @return Column
	 *
	 * @throws InternalError
	 * @throws PermissionError|NotFoundError
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

		?string $usergroupDefault,
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $showUserStatus,
		
		array $selectedViewIds = []
	):Column {
		// security
		if ($viewId) {
			try {
				$view = $this->viewService->find($viewId);
			} catch (InternalError|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			} catch (PermissionError $e) {
				throw new PermissionError('Can not load given view, no permission.');
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			try {
				$table = $this->tableMapper->find($view->getTableId());
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		} elseif ($tableId) {
			try {
				$table = $this->tableMapper->find($tableId);
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		} else {
			throw new InternalError('Cannot create column without table or view in context');
		}

		if (!$this->permissionsService->canCreateColumns($table)) {
			throw new PermissionError('create column for the table id = '.$table->getId().' is not allowed.');
		}

		$time = new DateTime();
		$item = new Column();
		$item->setTitle($title);
		$item->setTableId($table->getId());
		$item->setType($type);
		$item->setSubtype($subtype !== null ? $subtype: '');
		$item->setMandatory($mandatory);
		$item->setDescription($description ?? '');
		$item->setTextDefault($textDefault);
		$item->setTextAllowedPattern($textAllowedPattern);
		$item->setTextMaxLength($textMaxLength);
		$item->setNumberDefault($numberDefault);
		$item->setNumberMin($numberMin);
		$item->setNumberMax($numberMax);
		$item->setNumberDecimals($numberDecimals);
		$item->setNumberPrefix($numberPrefix !== null ? $numberPrefix: '');
		$item->setNumberSuffix($numberSuffix !== null ? $numberSuffix: '');
		$this->updateMetadata($item, $userId, true);
		$item->setSelectionOptions($selectionOptions);
		$item->setSelectionDefault($selectionDefault);
		$item->setDatetimeDefault($datetimeDefault);
		$item->setUsergroupDefault($usergroupDefault);
		$item->setUsergroupMultipleItems($usergroupMultipleItems);
		$item->setUsergroupSelectUsers($usergroupSelectUsers);
		$item->setUsergroupSelectGroups($usergroupSelectGroups);
		$item->setShowUserStatus($showUserStatus);

		try {
			$entity = $this->mapper->insert($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		if(isset($view) && $view) {
			// Add columns to view(s)
			$this->viewService->update($view->getId(), ['columns' => json_encode(array_merge($view->getColumnsArray(), [$entity->getId()]))], $userId, true);
		}
		foreach ($selectedViewIds as $viewId) {
			try {
				$view = $this->viewService->find($viewId);
			} catch (InternalError|MultipleObjectsReturnedException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			} catch (PermissionError $e) {
				throw new PermissionError('Can not add column to view, no permission.');
			} catch (DoesNotExistException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			$this->viewService->update($viewId, ['columns' => json_encode(array_merge($view->getColumnsArray(), [$entity->getId()]))], $userId, true);
		}
		return $this->enhanceColumn($entity);
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
	 * @param string|null $usergroupDefault
	 * @param bool|null $usergroupMultipleItems
	 * @param bool|null $usergroupSelectUsers
	 * @param bool|null $usergroupSelectGroups
	 * @param bool|null $showUserStatus
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

		?string $datetimeDefault,

		?string $usergroupDefault,
		?bool $usergroupMultipleItems,
		?bool $usergroupSelectUsers,
		?bool $usergroupSelectGroups,
		?bool $showUserStatus,
	):Column {
		try {
			/** @var Column $item */
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
			$item->setDescription($description);
			$item->setTextDefault($textDefault);
			$item->setTextAllowedPattern($textAllowedPattern);
			$item->setTextMaxLength($textMaxLength);
			$item->setNumberDefault($numberDefault);
			$item->setNumberMin($numberMin);
			$item->setNumberMax($numberMax);
			$item->setNumberDecimals($numberDecimals);
			if ($selectionOptions !== null) {
				$item->setSelectionOptions($selectionOptions);
			}
			if ($selectionDefault !== null) {
				$item->setSelectionDefault($selectionDefault);
			}
			$item->setDatetimeDefault($datetimeDefault);

			if ($usergroupDefault !== null) {
				$item->setUsergroupDefault($usergroupDefault);
			}
			$item->setUsergroupMultipleItems($usergroupMultipleItems);
			$item->setUsergroupSelectUsers($usergroupSelectUsers);
			$item->setUsergroupSelectGroups($usergroupSelectGroups);
			$item->setShowUserStatus($showUserStatus);

			$this->updateMetadata($item, $userId);
			return $this->enhanceColumn($this->mapper->update($item));
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	private function updateMetadata(Column $column, ?string $userId, bool $setCreateData = false): void {
		if ($userId) {
			$column->setLastEditBy($userId);
		} else {
			if ($this->userId) {
				$column->setLastEditBy($this->userId);
			} else {
				$e = new Exception('Could not update LastEditBy, no user id given.');
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
		$time = new DateTime();
		$column->setLastEditAt($time->format('Y-m-d H:i:s'));

		if ($setCreateData) {
			$column->setCreatedAt($time->format('Y-m-d H:i:s'));
			if ($userId) {
				$column->setCreatedBy($userId);
			} else {
				if ($this->userId) {
					$column->setCreatedBy($this->userId);
				} else {
					$e = new Exception('Could not update CreatedBy, no user id given.');
					$this->logger->error($e->getMessage(), ['exception' => $e]);
				}
			}
		}
	}

	/**
	 * @param int $id
	 * @param bool $skipRowCleanup
	 * @param null|string $userId
	 * @return Column
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function delete(int $id, bool $skipRowCleanup = false, ?string $userId = null): Column {
		try {
			/** @var Column $item */
			$item = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		// security
		if (!$this->permissionsService->canDeleteColumnsByTableId($item->getTableId(), $userId)) {
			throw new PermissionError('delete column id = '.$id.' is not allowed.');
		}

		if (!$skipRowCleanup) {
			try {
				$this->rowService->deleteColumnDataFromRows($item);
			} catch (InternalError $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			try {
				$table = $this->tableMapper->find($item->getTableId());
			} catch (DoesNotExistException|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
			$this->viewService->deleteColumnDataFromViews($id, $table);
		}

		try {
			$this->mapper->delete($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		return $this->enhanceColumn($item);
	}

	/**
	 * @param int|null $tableId
	 * @param int|null $viewId
	 * @param array $titles example ['Test column 1', 'And so on', '3rd column title']
	 * @param array $dataTypes example ['datetime', 'number', 'text']
	 * @param string|null $userId
	 * @param bool $createUnknownColumns
	 * @param int $countCreatedColumns
	 * @param int $countMatchingColumns
	 * @return array with column object or null for given columns
	 * @throws DoesNotExistException
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function findOrCreateColumnsByTitleForTableAsArray(?int $tableId, ?int $viewId, array $titles, array $dataTypes, ?string $userId, bool $createUnknownColumns, int &$countCreatedColumns, int &$countMatchingColumns): array {
		$result = [];

		if($userId === null) {
			$userId = $this->userId;
		}
		if ($viewId) {
			$allColumns = $this->findAllByView($viewId, $userId);
		} elseif ($tableId) {
			$allColumns = $this->findAllByTable($tableId, null, $userId);
		} else {
			$e = new Exception('Either tableId nor viewId is given.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

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
				$result[$i] = $this->create(
					$userId,
					$tableId,
					$viewId,
					$dataTypes[$i]['type'],
					$dataTypes[$i]['subtype'] ?? '',
					$title,
					false,
					$description,
					null,
					null,
					null,
					$dataTypes[$i]['number_prefix'] ?? null,
					$dataTypes[$i]['number_suffix'] ?? null,
					null,
					null,
					null,
					$dataTypes[$i]['number_decimals'] ?? null,
					null,
					$dataTypes[$i]['selection_default'] ?? null,
					null,
					null,
					null,
					null,
					null,
					null,
					[]
				);
				$countCreatedColumns++;
			}
		}
		return $this->enhanceColumns($result);
	}

	/**
	 * @param int $tableId
	 * @return int
	 * @throws PermissionError
	 */
	public function getColumnsCount(int $tableId): int {
		if ($this->permissionsService->canReadColumnsByTableId($tableId)) {
			return $this->mapper->countColumns($tableId);
		} else {
			throw new PermissionError('no read access for counting to table id = '.$tableId);
		}
	}

	/**
	 * add some basic values related to this column in context
	 *
	 * $userId can be set or ''
	 * @param Column $column
	 *
	 * @return Column
	 */
	private function enhanceColumn(Column $column): Column {
		// add created by display name for UI usage
		$column->setCreatedByDisplayName($this->userHelper->getUserDisplayName($column->getCreatedBy()));
		$column->setLastEditByDisplayName($this->userHelper->getUserDisplayName($column->getLastEditBy()));
		return $column;
	}

	private function enhanceColumns(?array $columns): array {
		if ($columns === null) {
			return [];
		}

		foreach ($columns as $column) {
			$this->enhanceColumn($column);
		}
		return $columns;
	}
}
