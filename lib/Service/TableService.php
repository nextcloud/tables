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
	 * Find all tables for a user
	 *
	 * takes the user from actual context or the given user
	 * it is possible to get all tables, but only if requested by cli
	 *
	 * @param string|null $userId (null -> take from session, '' -> no user in context)
	 * @param bool $skipTableEnhancement
	 * @param bool $skipSharedTables
	 * @return array<Table>
	 * @throws InternalError
	 */
	public function findAll(?string $userId = null, bool $skipTableEnhancement = false, bool $skipSharedTables = false): array {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$ownTables = $this->mapper->findAll($userId);
			if (!$skipSharedTables && $userId !== '') {
				$sharedTables = $this->shareService->findTablesSharedWithMe($userId);

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
			} else {
				$newSharedTables = [];
			}
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		// enhance table objects with additional data
		$allTables = array_merge($ownTables, $newSharedTables);
		if (!$skipTableEnhancement) {
			foreach ($allTables as $table) {
				/** @var string $userId */
				$this->enhanceTable($table, $userId);
			}
		}

		return $allTables;
	}

	/**
	 * add some basic values related to this table in context
	 *
	 * $userId can be set or ''
	 *
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function enhanceTable(Table &$table, string $userId): void {
		// add owner display name for UI
		$this->addOwnerDisplayName($table);

		// set hasShares if this table is shared by you (you share it with somebody else)
		// (senseless if we have no user in context)
		if ($userId !== '') {
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
		// (senseless if we have no user in context)
		if ($userId !== '') {
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
	}


	/**
	 * @param int $id
	 * @param string|null $userId
	 * @param bool $skipTableEnhancement
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function find(int $id, ?string $userId = null, bool $skipTableEnhancement = false): Table {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$table = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canReadTable($table, $userId)) {
				throw new PermissionError('PermissionError: can not read table with id '.$id);
			}

			if (!$skipTableEnhancement) {
				/** @var string $userId */
				$this->enhanceTable($table, $userId);
			}

			return $table;
		} catch (DoesNotExistException $e) {
			$this->logger->warning($e->getMessage());
			throw new NotFoundError($e->getMessage());
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param string $term
	 * @param int $limit
	 * @param int $offset
	 * @param string|null $userId
	 * @return array
	 */
	public function search(string $term, int $limit = 100, int $offset = 0, ?string $userId = null): array {
		try {
			/** @var string $userId */
			$userId = $this->permissionsService->preCheckUserId($userId);
			$tables = $this->mapper->search($term, $userId, $limit, $offset);
			foreach ($tables as &$table) {
				/** @var string $userId */
				$this->enhanceTable($table, $userId);
			}
			return $tables;
		} catch (InternalError | \OCP\DB\Exception $e) {
			return [];
		}
	}

	/**
	 * @noinspection PhpUndefinedMethodInspection
	 *
	 * @throws \OCP\DB\Exception
	 * @throws InternalError|PermissionError
	 */
	public function create(string $title, string $template, string $emoji, ?string $userId = null): Table {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId, false); // $userId is set

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
			$table = $this->tableTemplateService->makeTemplate($newTable, $template);
			$this->enhanceTable($table, $userId);
			return $table;
		}
		return $this->addOwnerDisplayName($newTable);
	}

	/**
	 * @noinspection PhpUndefinedMethodInspection
	 *
	 * @param int $id$userId
	 * @param string $title
	 * @param string $emoji
	 * @param string|null $userId
	 * @return Table
	 * @throws InternalError
	 */
	public function update(int $id, string $title, string $emoji, ?string $userId): Table {
		$userId = $this->permissionsService->preCheckUserId($userId, false); // $userId is set

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
			/** @var string $userId */
			$this->enhanceTable($table, $userId);
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
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId is set or ''

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


	// PRIVATE FUNCTIONS ---------------------------------------------------------------

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
