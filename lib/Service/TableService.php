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

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, string $userId,
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
	 * @param string|null $userId
	 * @return array<Table>
	 * @throws InternalError
	 */
	public function findAll(?string $userId = null): array {
		if ($userId === null) {
			$userId = $this->userId;
		}

		try {
			$ownTables = $this->mapper->findAll($userId);
			$sharedTables = $this->shareService->findTablesSharedWithMe();

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
		return $this->addOwnersDisplayName(array_merge($ownTables, $newSharedTables));
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
			$item = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canUpdateTable($item)) {
				throw new PermissionError('PermissionError: can not update table with id '.$id);
			}

			$time = new DateTime();
			$item->setTitle($title);
			$item->setEmoji($emoji);
			$item->setLastEditBy($userId);
			$item->setLastEditAt($time->format('Y-m-d H:i:s'));
			return $this->addOwnerDisplayName($this->mapper->update($item));
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
	 * @param mixed $tables
	 */
	private function addOwnerDisplayName($tables): Table {
		$tables->setOwnerDisplayName($this->userHelper->getUserDisplayName($tables->getOwnership()));
		return $tables;
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
