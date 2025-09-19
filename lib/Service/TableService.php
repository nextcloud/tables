<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @noinspection DuplicatedCode */

namespace OCA\Tables\Service;

use DateTime;
use OCA\Tables\Activity\ActivityManager;
use OCA\Tables\Activity\ChangeSet;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Event\TableDeletedEvent;
use OCA\Tables\Event\TableOwnershipTransferredEvent;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\TableScheme;
use OCA\Tables\ResponseDefinitions;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as OcpDbException;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesTable from ResponseDefinitions
 */
class TableService extends SuperService {

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		private TableMapper $mapper,
		private TableTemplateService $tableTemplateService,
		private ColumnService $columnService,
		private RowService $rowService,
		private ViewService $viewService,
		private ShareService $shareService,
		protected UserHelper $userHelper,
		protected FavoritesService $favoritesService,
		protected IEventDispatcher $eventDispatcher,
		private ContextService $contextService,
		protected IAppManager $appManager,
		protected IL10N $l,
		protected Defaults $themingDefaults,
		private ActivityManager $activityManager,
	) {
		parent::__construct($logger, $userId, $permissionsService);
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
	 * @param bool $createTutorial
	 * @return array<Table>
	 * @throws InternalError
	 */
	public function findAll(?string $userId = null, bool $skipTableEnhancement = false, bool $skipSharedTables = false, bool $createTutorial = true): array {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''
		$allTables = [];

		try {
			$ownedTables = $this->mapper->findAll($userId); // get own tables
			foreach ($ownedTables as $ownedTable) {
				$allTables[$ownedTable->getId()] = $ownedTable;
			}
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}

		// if there are no own tables found, create the tutorial table
		if (count($allTables) === 0 && $createTutorial) {
			try {
				$productName = $this->themingDefaults->getName();
				$tutorialTable = $this->create($this->l->t('Welcome to %s Tables!', [$productName]), 'tutorial', '🚀');
				$allTables[$tutorialTable->getId()] = $tutorialTable;
			} catch (InternalError|PermissionError|DoesNotExistException|MultipleObjectsReturnedException|OcpDbException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		}

		if (!$skipSharedTables && $userId !== '') {
			$sharedTables = $this->shareService->findTablesSharedWithMe($userId);

			// clean duplicates
			foreach ($sharedTables as $sharedTable) {
				if (!isset($allTables[$sharedTable->getId()])) {
					$allTables[$sharedTable->getId()] = $sharedTable;
				}
			}
		}

		$contexts = $this->contextService->findAll($userId);
		foreach ($contexts as $context) {
			$nodes = $context->getNodes();
			foreach ($nodes as $node) {
				if ($node['node_type'] !== Application::NODE_TYPE_TABLE
					|| isset($allTables[$node['node_id']])
				) {
					continue;
				}
				$allTables[$node['node_id']] = $this->find($node['node_id'], $skipTableEnhancement, $userId);
			}
		}

		// enhance table objects with additional data
		if (!$skipTableEnhancement) {
			foreach ($allTables as $table) {
				/** @var string $userId */
				try {
					$this->enhanceTable($table, $userId);
				} catch (InternalError|PermissionError $e) {
					$this->logger->error($e->getMessage(), ['exception' => $e]);
				}
				// if the table is shared with me, there are no other shares
				// will avoid showing the shared icon in the FE nav
				if ($table->getIsShared()) {
					$table->setHasShares(false);
				}
			}
		}

		return array_values($allTables);
	}

	/**
	 * @param Table[] $tables
	 * @return TablesTable[]
	 */
	public function formatTables(array $tables): array {
		return array_map(fn (Table $table) => $table->jsonSerialize(), $tables);
	}

	/**
	 * add some basic values related to this table in context
	 *
	 * $userId can be set or ''
	 * @param Table $table
	 * @param string $userId
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function enhanceTable(Table $table, string $userId): void {
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
		} catch (PermissionError $e) {
			$table->setRowsCount(0);
		}

		// add the column count
		try {
			$table->setColumnsCount($this->columnService->getColumnsCount($table->getId()));
		} catch (PermissionError $e) {
			$table->setColumnsCount(0);
		}

		// set if this is a shared table with you (somebody else shared it with you)
		// (senseless if we have no user in context)
		if ($userId !== '' && $userId !== $table->getOwnership()) {
			try {
				$permissions = $this->shareService->getSharedPermissionsIfSharedWithMe($table->getId(), 'table', $userId);
				$table->setIsShared(true);
				$table->setOnSharePermissions($permissions);
			} catch (NotFoundError $e) {
				try {
					$table->setOnSharePermissions($this->permissionsService->getPermissionArrayForNodeFromContexts($table->getId(), 'table', $userId));
					$table->setIsShared(true);
				} catch (NotFoundError $e) {
				}

			}
		}
		if (!$table->getIsShared() || $table->getOnSharePermissions()->manage) {
			// add the corresponding views if it is an own table, or you have table manage rights
			$table->setViews($this->viewService->findAll($table));
		}

		if ($this->favoritesService->isFavorite(Application::NODE_TYPE_TABLE, $table->getId())) {
			$table->setFavorite(true);
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
	public function find(int $id, bool $skipTableEnhancement = false, ?string $userId = null): Table {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$table = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canReadTable($table, $userId)) {
				throw new PermissionError('PermissionError: can not read table with id ' . $id);
			}

			if (!$skipTableEnhancement) {
				$this->enhanceTable($table, $userId);
			}

			return $table;
		} catch (DoesNotExistException $e) {
			$this->logger->warning($e->getMessage());
			throw new NotFoundError($e->getMessage());
		} catch (MultipleObjectsReturnedException|OcpDbException $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param string $title
	 * @param string $template
	 * @param string|null $emoji
	 * @param string|null $userId
	 * @return Table
	 * @throws InternalError
	 * @noinspection DuplicatedCode
	 */
	public function create(string $title, string $template, ?string $emoji, ?string $description = '', ?string $userId = null): Table {
		$userId = $this->permissionsService->preCheckUserId($userId, false); // we can assume that the $userId is set

		$time = new DateTime();
		$item = new Table();
		$item->setTitle($title);
		$item->setDescription($description);
		if ($emoji) {
			$item->setEmoji($emoji);
		}
		$item->setOwnership($userId);
		$item->setCreatedBy($userId);
		$item->setLastEditBy($userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			$newTable = $this->mapper->insert($item);
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
		if ($template !== 'custom') {
			try {
				$table = $this->tableTemplateService->makeTemplate($newTable, $template);
			} catch (InternalError|PermissionError|DoesNotExistException|MultipleObjectsReturnedException|OcpDbException $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		} else {
			$table = $this->addOwnerDisplayName($newTable);
		}

		try {
			$this->enhanceTable($table, $userId);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		$this->activityManager->triggerEvent(
			objectType: ActivityManager::TABLES_OBJECT_TABLE,
			object: $table,
			subject: ActivityManager::SUBJECT_TABLE_CREATE,
			additionalParams: [],
			author: $userId
		);
		return $table;
	}

	/**
	 * Set a new owner for a table and adjust all related ressources
	 *
	 * @param int $id
	 * @param string $newOwnerUserId
	 * @param string|null $userId
	 *
	 * @return Table
	 *
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function setOwner(int $id, string $newOwnerUserId, ?string $userId = null): Table {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$table = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (MultipleObjectsReturnedException|OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security
		if (!$this->permissionsService->canChangeElementOwner($table, $userId)) {
			throw new PermissionError('PermissionError: can not change table owner with table id ' . $id);
		}

		$table->setOwnership($newOwnerUserId);
		try {
			$table = $this->mapper->update($table);
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// change owners of related shares
		try {
			$this->shareService->changeSenderForNode('table', $id, $newOwnerUserId, $userId);
		} catch (InternalError $e) {
			$this->logger->error('Could not update related shares for a table transfer!');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$event = new TableOwnershipTransferredEvent(
			table: $table,
			toUserId: $newOwnerUserId,
			fromUserId: $userId
		);

		$this->eventDispatcher->dispatchTyped($event);

		return $table;
	}

	/**
	 * @param int $id
	 * @param null|string $userId
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function delete(int $id, ?string $userId = null): Table {
		$userId = $this->permissionsService->preCheckUserId($userId); // assume that $userId is set or ''

		try {
			$item = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (MultipleObjectsReturnedException|OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security
		if (!$this->permissionsService->canManageTable($item, $userId)) {
			throw new PermissionError('PermissionError: can not delete table with id ' . $id);
		}

		// delete all rows for that table
		try {
			$this->rowService->deleteAllByTable($id, $userId);
		} catch (PermissionError|OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// delete all views for that table
		// we must delete views before columns because we need columns
		// while deleting views (in case we're deleting a table that has views)
		try {
			$this->viewService->deleteAllByTable($item, $userId);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// delete all columns for that table
		try {
			$columns = $this->columnService->findAllByTable($id, $userId);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		foreach ($columns as $column) {
			try {
				$this->columnService->delete($column->id, true, $userId);
			} catch (InternalError $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		}


		// delete all shares for that table
		$this->shareService->deleteAllForTable($item);

		// delete node relations if view is in any context
		$this->contextService->deleteNodeRel($id, Application::NODE_TYPE_TABLE);

		// delete table
		try {
			$this->mapper->delete($item);
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$event = new TableDeletedEvent(table: $item);

		$this->eventDispatcher->dispatchTyped($event);
		$this->activityManager->triggerEvent(
			objectType: ActivityManager::TABLES_OBJECT_TABLE,
			object: $item,
			subject: ActivityManager::SUBJECT_TABLE_DELETE,
			additionalParams: [],
			author: $userId
		);

		return $item;
	}

	/**
	 *
	 * @param int $id $userId
	 * @param string|null $title
	 * @param string|null $emoji
	 * @param string|null $userId
	 * @return Table
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function update(int $id, ?string $title, ?string $emoji, ?string $description, ?bool $archived = null, ?string $userId = null): Table {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$table = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (MultipleObjectsReturnedException|OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security
		if (!$this->permissionsService->canUpdateTable($table, $userId)) {
			throw new PermissionError('PermissionError: can not update table with id ' . $id);
		}

		$changes = new ChangeSet($table);
		$time = new DateTime();
		if ($title !== null) {
			$table->setTitle($title);
		}
		if ($emoji !== null) {
			$table->setEmoji($emoji);
		}
		if ($archived !== null) {
			$table->setArchived($archived);
		}
		if ($description !== null) {
			$table->setDescription($description);
		}
		$table->setLastEditBy($userId);
		$table->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			$table = $this->mapper->update($table);
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		try {
			$this->enhanceTable($table, $userId);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		$changes->setAfter($table);
		$this->activityManager->triggerUpdateEvents(
			objectType: ActivityManager::TABLES_OBJECT_TABLE,
			changeSet: $changes,
			subject: ActivityManager::SUBJECT_TABLE_UPDATE
		);
		return $table;
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
				$this->enhanceTable($table, $userId);
			}
			return $tables;
		} catch (InternalError|PermissionError|OcpDbException $e) {
			return [];
		}
	}

	/**
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function getScheme(int $id, ?string $userId = null): TableScheme {
		$columns = $this->columnService->findAllByTable($id);
		$table = $this->find($id);
		$this->enhanceTable($table, $userId);
		return new TableScheme($table->getTitle(), $table->getEmoji(), $columns, $table->getViews() ?: [], $table->getDescription() ?: '', $this->appManager->getAppVersion('tables'));
	}

	// PRIVATE FUNCTIONS ---------------------------------------------------------------

	/**
	 * @param Table $table
	 * @return Table
	 */
	private function addOwnerDisplayName(Table $table): Table {
		$table->setOwnerDisplayName($this->userHelper->getUserDisplayName($table->getOwnership()));
		return $table;
	}
}
