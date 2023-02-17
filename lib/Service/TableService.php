<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use Psr\Log\LoggerInterface;

class TableService extends SuperService {
	private TableMapper $mapper;

	private TableTemplateService $tableTemplateService;

	private ColumnService $columnService;

	private RowService $rowService;

	private ShareService $shareService;

	protected UserHelper $userHelper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
								TableMapper $mapper, TableTemplateService $tableTemplateService, ColumnService $columnService, RowService $rowService, ShareService $shareService, UserHelper $userHelper) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
		$this->tableTemplateService = $tableTemplateService;
		$this->columnService = $columnService;
		$this->rowService = $rowService;
		$this->shareService = $shareService;
		$this->userHelper = $userHelper;
	}

	/**
	 * @throws InternalError
	 */
	public function findAllForAdmins(?bool $skipTableEnhancement = false): array {
		try {
			$tables = $this->mapper->findAll();
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		// enhance table objects with additional data
		if (!$skipTableEnhancement) {
			foreach ($tables as $table) {
				$this->enhanceTable($table);
			}
		}

		return $tables;
	}

	/**
	 * @param string|null $userId
	 * @param bool|null $skipTableEnhancement
	 * @param bool|null $skipSharedTables
	 * @return array<Table>
	 * @throws InternalError
	 */
	public function findAll(?string $userId = null, ?bool $skipTableEnhancement = false, ?bool $skipSharedTables = false): array {
		if ($userId === null) {
			$userId = $this->userId;
		}

		try {
			$ownTables = $this->mapper->findAll($userId);
			if (!$skipSharedTables) {
				$sharedTables = $this->shareService->findTablesSharedWithMe($userId);
			}

			// clean duplicates
			$newSharedTables = [];
			foreach ($sharedTables as $sharedTable) {
				$found = false;
				foreach ($ownTables as $ownTable) {
					if ($sharedTable->getId() === $ownTable->getId()) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$newSharedTables[] = $sharedTable;
				}
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		// enhance table objects with additional data
		$allTables = array_merge($ownTables, $newSharedTables);
		if (!$skipTableEnhancement) {
			foreach ($allTables as $table) {
				$this->enhanceTable($table);
			}
		}

		return $allTables;
	}

	/** @noinspection PhpUndefinedMethodInspection */
	private function enhanceTable(Table &$table): void {
		// add owner display name for UI
		$this->addOwnerDisplayName($table);

		// set if this table is shared by you (you share it with somebody else)
		// a table can have other shares, we are looking here for shares from the userId in context
		// (only if userId is given, otherwise it's an anonymize call maybe from the occ -> no shares relevant
		if ($this->userId) {
			try {
				$shares = $this->shareService->findAll('table', $table->getId());
				$table->setHasShares(count($shares) !== 0);
			} catch (InternalError $e) {
			}
		}

		// add the rows count
		try {
			$table->setRowsCount($this->rowService->getRowsCount($table->getId()));
		} catch (InternalError|PermissionError $e) {
			$table->setRowsCount(0);
		}

		// set if this is a shared table with you (somebody else shared it with you)
		try {
			$share = $this->shareService->findTableShareIfSharedWithMe($table->getId());
			/** @noinspection PhpUndefinedMethodInspection */
			$table->setIsShared(true);
			/** @noinspection PhpUndefinedMethodInspection */
			$table->setOnSharePermissions([
				'read' => $share->getPermissionRead(),
				'create' => $share->getPermissionCreate(),
				'update' => $share->getPermissionUpdate(),
				'delete' => $share->getPermissionDelete(),
				'manage' => $share->getPermissionManage(),
			]);
		} catch (\Exception $e) {
		}
	}


	/**
	 * @param int $id
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function find(int $id): Table {
		try {
			$table = $this->mapper->find($id);
			$this->enhanceTable($table);

			// security
			if (!$this->permissionsService->canReadTable($table)) {
				throw new PermissionError('PermissionError: can not read table with id '.$id);
			}

			return $this->addOwnerDisplayName($table);
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
	 *
	 * @throws \OCP\DB\Exception
	 * @throws InternalError|PermissionError
	 */
	public function create(string $title, string $template, string $emoji): Table {
		$userId = $this->userId;
		$time = new DateTime();
		$item = new Table();
		$item->setTitle($title);
		$item->setEmoji($emoji);
		$item->setOwnership($userId);
		$item->setCreatedBy($userId);
		$item->setLastEditBy($userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			$newTable = $this->mapper->insert($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
		if ($template !== 'custom') {
			return $this->tableTemplateService->makeTemplate($newTable, $template);
		}
		return $this->addOwnerDisplayName($newTable);
	}

	/**
	 * @noinspection PhpUndefinedMethodInspection
	 *
	 * @param int $id
	 * @param string $title
	 * @param string $emoji
	 * @param string $userId
	 * @return Table
	 * @throws InternalError
	 */
	public function update(int $id, string $title, string $emoji, string $userId): Table {
		try {
			$table = $this->mapper->find($id);
			// $this->enhanceTable($table);

			// security
			if (!$this->permissionsService->canUpdateTable($table)) {
				throw new PermissionError('PermissionError: can not update table with id '.$id);
			}

			$time = new DateTime();
			$table->setTitle($title);
			$table->setEmoji($emoji);
			$table->setLastEditBy($userId);
			$table->setLastEditAt($time->format('Y-m-d H:i:s'));
			$table = $this->mapper->update($table);
			$this->enhanceTable($table);
			return $table;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $id
	 * @param null|string $userId
	 * @return Table
	 * @throws InternalError
	 */
	public function delete(int $id, ?string $userId = null): Table {
		try {
			$item = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canDeleteTable($item, $userId)) {
				throw new PermissionError('PermissionError: can not delete table with id '.$id);
			}

			// delete all rows for that table
			$this->rowService->deleteAllByTable($id, $userId);

			// delete all columns for that table
			$columns = $this->columnService->findAllByTable($id);
			foreach ($columns as $column) {
				$this->columnService->delete($column->id, true, $userId);
			}

			// delete all shares for that table
			$this->shareService->deleteAllForTable($item);

			// delete table
			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @noinspection PhpUndefinedMethodInspection
	 *
	 * @param Table $table
	 * @return Table
	 */
	private function addOwnerDisplayName(Table $table): Table {
		$table->setOwnerDisplayName($this->userHelper->getUserDisplayName($table->getOwnership()));
		return $table;
	}

	private function addOwnersDisplayName(array $tables): array {
		$return = [];
		foreach ($tables as $table) {
			$table->setOwnerDisplayName($this->userHelper->getUserDisplayName($table->getOwnership()));
			$return[] = $table;
		}
		return $return;
	}
}
