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
use OCA\Tables\Dto\Column as ColumnDto;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Event\TableDeletedEvent;
use OCA\Tables\Event\TableOwnershipTransferredEvent;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\ColumnSettings;
use OCA\Tables\Model\FilterSet;
use OCA\Tables\Model\Permissions;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\Model\TableScheme;
use OCA\Tables\Model\ViewUpdateInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ValueObject\Title;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception as OcpDbException;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesTable from ResponseDefinitions
 */
class TableService extends SuperService {
	use TTransactional;

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
		private IDBConnection $dbc,
		protected IL10N $l,
		protected Defaults $themingDefaults,
		private ActivityManager $activityManager,
		private FederationService $federationService,
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
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
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
				$allTables[$node['node_id']] = $this->find($node['node_id'], true, $userId);
			}
		}

		// enhance table objects with additional data
		if (!$skipTableEnhancement) {
			/** @var string $userId */
			try {
				$this->enhanceTables($allTables, $userId);
			} catch (InternalError|PermissionError $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
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
	 * Adds some basic values related to a list of tables in context (rows count,
	 * columns count, shares count, etc.)
	 *
	 * $userId can be set or ''
	 *
	 * @param Table[] $tables
	 * @param string $userId
	 * @throws InternalError
	 * @throws PermissionError
	 */
	private function enhanceTables(array $tables, string $userId): void {
		if (empty($tables)) {
			return;
		}

		$tablesById = [];
		foreach ($tables as $table) {
			$tablesById[$table->getId()] = $table;
		}
		$tableIds = array_keys($tablesById);

		// add owner display names for UI
		$ownerIds = array_values(array_filter(
			array_unique(array_map(static fn (Table $table) => $table->getOwnership(), $tables))
		));
		$ownerDisplayNames = $this->userHelper->getUsersDisplayNames($ownerIds);
		foreach ($tables as $table) {
			$ownerId = $table->getOwnership() ?? '';
			$table->setOwnerDisplayName($ownerDisplayNames[$ownerId] ?? $ownerId);
		}

		// add the rows and columns counts
		$rowsCounts = $this->rowService->getRowsCountForTables($tableIds, $userId);
		$columnsCounts = $this->columnService->getColumnsCountForTables($tableIds, $userId);
		foreach ($tables as $table) {
			$table->setRowsCount($rowsCounts[$table->getId()] ?? 0);
			$table->setColumnsCount($columnsCounts[$table->getId()] ?? 0);
		}

		// set hasShares / public isShared in one batch
		if ($userId === '') {
			$linkShareTableIds = $this->shareService->getTableIdsWithLinkShares($tableIds);
			$linkShareTableIds = array_flip($linkShareTableIds);
			foreach ($tables as $table) {
				$table->setIsShared(isset($linkShareTableIds[$table->getId()]));
				$table->setOnSharePermissions(new Permissions(read: true));
			}
		} else {
			$ownedTableIds = array_filter($tableIds, static fn (int $id) => $tablesById[$id]->getOwnership() === $userId);
			$sharesCount = $this->shareService->countSharesForTables($ownedTableIds, $userId);
			foreach ($tables as $table) {
				$table->setHasShares(($sharesCount[$table->getId()] ?? 0) > 0);
			}
		}

		// set isShared and onSharePermissions (kept as is, per table)
		if ($userId !== '') {
			foreach ($tables as $table) {
				try {
					$this->setIsSharedState($table, $userId);
				} catch (InternalError|PermissionError $e) {
					$this->logger->error($e->getMessage(), ['exception' => $e]);
					$table->setIsShared(false);
					$table->setOnSharePermissions(new Permissions());
				}
			}
		}

		// if the table is shared with me, there are no other shares
		// will avoid showing the shared icon in the FE nav
		foreach ($tables as $table) {
			if ($table->getIsShared()) {
				$table->setHasShares(false);
			}
		}

		// add the corresponding views if it is an own table, or you have table manage rights
		$tablesToLoadViews = [];
		foreach ($tables as $table) {
			if (!$table->getIsShared() || $table->getOnSharePermissions()->manage) {
				$tablesToLoadViews[] = $table;
			}
		}

		try {
			$viewsByTable = $this->viewService->findForTables($tablesToLoadViews, $userId, $rowsCounts);
			foreach ($tablesToLoadViews as $table) {
				$table->setViews($viewsByTable[$table->getId()] ?? []);
			}
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			foreach ($tablesToLoadViews as $table) {
				$table->setViews([]);
			}
		}

		// add favorites
		foreach ($tables as $table) {
			if ($this->favoritesService->isFavorite(Application::NODE_TYPE_TABLE, $table->getId())) {
				$table->setFavorite(true);
			}
		}
	}

	private function setIsSharedState(Table $table, string $userId): void {
		// set if this is a shared table with you (somebody else shared it with you)
		// (senseless if we have no user in context)
		if ($userId !== '' && $userId !== $table->getOwnership()) {
			try {
				$permissions = $this->shareService->getSharedPermissionsIfSharedWithMe($table->getId(), 'table', $userId);
				$table->setIsShared(true);
				$table->setOnSharePermissions($permissions);
			} catch (NotFoundError) {
				try {
					$table->setOnSharePermissions($this->permissionsService->getPermissionArrayForNodeFromContexts($table->getId(), 'table', $userId));
					$table->setIsShared(true);
				} catch (NotFoundError) {
				}
			}
		} else {
			$table->setIsShared($userId === '' && $this->shareService->hasLinkShare($table));
			$table->setOnSharePermissions(new Permissions(read: true));
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
				$this->enhanceTables([$table], $userId);
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
	 * @throws \InvalidArgumentException
	 * @noinspection DuplicatedCode
	 */
	public function create(string $title, string $template, ?string $emoji, ?string $description = '', ?string $userId = null, ?string $uuid = null): Table {
		$userId = $this->permissionsService->preCheckUserId($userId, false); // we can assume that the $userId is set
		$title = (string)new Title($title);

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

		// avoid running into a UniqueConstraintViolation, because we may be
		// inside a transaction, that otherwise might be canceled.
		// Alternative approach would be to run this in a nested transaction,
		// which are possible, but not encouraged.
		// The chance is small, and the user just can click on import once more.
		$applicableUuid = null;
		if ($uuid !== null) {
			try {
				$this->mapper->findByUuid($uuid);
			} catch (DoesNotExistException) {
				$applicableUuid = $uuid;
			}
		}
		$item->setUuid($applicableUuid);

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
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		} else {
			$table = $newTable;
		}

		try {
			$this->enhanceTables([$table], $userId);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
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
			throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (MultipleObjectsReturnedException|OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security
		if (!$this->permissionsService->canChangeElementOwner($table, $userId)) {
			throw new PermissionError('PermissionError: can not change table owner with table id ' . $id);
		}

		$table->setOwnership($newOwnerUserId);

		try {
			$table = $this->atomic(function () use ($table, $id, $newOwnerUserId, $userId) {
				$table = $this->mapper->update($table);
				$this->shareService->changeSenderForNode('table', $id, $newOwnerUserId, $userId);
				return $table;
			}, $this->dbc);
		} catch (\Exception $e) {
			$this->logger->error('Failed to transfer table ownership: ' . $e->getMessage(), ['exception' => $e]);
			throw new InternalError('Failed to transfer table ownership: ' . $e->getMessage());
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
			throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (MultipleObjectsReturnedException|OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
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
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// delete all views for that table
		// we must delete views before columns because we need columns
		// while deleting views (in case we're deleting a table that has views)
		try {
			$this->viewService->deleteAllByTable($item, $userId);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// delete all columns for that table
		try {
			$columns = $this->columnService->findAllByTable($id, $userId, $item);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
		foreach ($columns as $column) {
			try {
				$this->columnService->delete($column->id, true, $userId);
			} catch (InternalError $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
		}

		// notify federated shares about table deletion
		$this->federationService->notifyNodeDelete($item, 'table');

		// delete all shares for that table
		$this->shareService->deleteAllForTable($item);

		// delete node relations if view is in any context
		$this->contextService->deleteNodeRel($id, Application::NODE_TYPE_TABLE);

		// delete table
		try {
			$this->mapper->delete($item);
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
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
	 * @throws \InvalidArgumentException
	 */
	public function update(int $id, ?string $title, ?string $emoji, ?string $description, ?bool $archived = null, ?string $userId = null, ?ColumnSettings $columnSettings = null, ?SortRuleSet $sort = null): Table {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$table = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (MultipleObjectsReturnedException|OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security
		if (!$this->permissionsService->canUpdateTable($table, $userId)) {
			throw new PermissionError('PermissionError: can not update table with id ' . $id);
		}

		$changes = new ChangeSet($table);
		$time = new DateTime();
		if ($title !== null) {
			$title = (string)new Title($title);
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
		if ($columnSettings !== null) {
			$table->setColumnOrder(\json_encode($columnSettings->jsonSerialize()));
		}
		if ($sort !== null) {
			$table->setSort(\json_encode($sort->jsonSerialize()));
		}
		$table->setLastEditBy($userId);
		$table->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			$table = $this->mapper->update($table);
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// notify federated shares about table update
		$this->federationService->notifyNodeUpdate($table, 'table');

		try {
			$this->enhanceTables([$table], $userId);
		} catch (InternalError|PermissionError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
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
			$this->enhanceTables($tables, $userId);
			return $tables;
		} catch (InternalError|PermissionError|OcpDbException) {
			return [];
		}
	}

	/**
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function getScheme(int $id, ?string $userId = null): TableScheme {
		$table = $this->find($id, skipTableEnhancement: true);
		$columns = $this->columnService->findAllByTable($id, null, $table);
		$this->enhanceTables([$table], $userId ?? '');
		return new TableScheme($table->getTitle(), $table->getEmoji(), $columns, $table->getViews() ?: [], $table->getDescription() ?: '', $this->appManager->getAppVersion('tables'), $table->getColumnOrderSettingsArray(), $table->getSortArray(), $table->getUuid());
	}

	// PRIVATE FUNCTIONS ---------------------------------------------------------------

	/**
	 * @param array $table
	 * @param string $userId
	 *
	 * @return Table
	 *
	 * @throws InternalError
	 */
	public function importTable(array $table, string $userId): Table {
		$now = (new DateTime())->format('Y-m-d H:i:s');
		$item = new Table();
		$item->setUuid((isset($table['uuid']) && Uuid::isValid($table['uuid'])) ? $table['uuid'] : null);
		$item->setTitle($table['title']);
		$item->setEmoji($table['emoji']);
		$item->setOwnership($userId);
		$item->setCreatedBy($userId);
		$item->setCreatedAt($table['createdAt'] ?? $now);
		$item->setLastEditBy($userId);
		$item->setLastEditAt($table['lastEditAt'] ?? $now);
		$item->setArchived((bool)$table['archived']);
		$item->setDescription($table['description']);
		try {
			$newTable = $this->mapper->insert($item);
		} catch (\Exception $e) {
			$this->logger->error('userMigrationImport insert error: ' . $e->getMessage());
			throw new InternalError('userMigrationImport insert error: ' . $e->getMessage());
		}
		return $newTable;
	}

	/**
	 * @param int $id
	 * @param array $updateScheme
	 *
	 * @return array
	 *
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 * @throws BadRequestError
	 */
	public function compareTableSchemeChanges(int $id, array $updateScheme): array {
		$table = $this->find($id);
		$this->validateTableScheme($updateScheme);
		$structureService = \OCP\Server::get(StructureService::class);
		$structureService->resolveChangesForTable($id, $updateScheme);

		$compareData = [
			'title' => [
				'from' => $table->getTitle(),
				'to' => $updateScheme['title'] ?? $table->getTitle(),
			],
			'emoji' => [
				'from' => $table->getEmoji(),
				'to' => $updateScheme['emoji'] ?? $table->getEmoji(),
			],
			'description' => [
				'from' => $table->getDescription(),
				'to' => $updateScheme['description'] ?? $table->getDescription(),
			],
			'columns' => [
				'addColumns' => $structureService->addedColumns(),
				'removeColumns' => $structureService->removedColumns(),
				'modifyColumns' => $structureService->modifiedColumns(),
			],
			'views' => [
				'addViews' => $structureService->addedViews(),
				'removeViews' => $structureService->removedViews(),
				'modifyViews' => $structureService->modifiedViews(),
			],
			'columnOrderChanges' => $structureService->columnOrderChanges(),
			'sortChanges' => $structureService->sortChanges(),
		];

		$compareData['hasChanges'] = !empty($compareData['columns']['addColumns'])
			|| !empty($compareData['columns']['removeColumns'])
			|| !empty($compareData['columns']['modifyColumns'])
			|| !empty($compareData['views']['addViews'])
			|| !empty($compareData['views']['removeViews'])
			|| !empty($compareData['views']['modifyViews'])
			|| !empty($compareData['columnOrderChanges'])
			|| !empty($compareData['sortChanges'])
			|| $compareData['title']['from'] !== $compareData['title']['to']
			|| $compareData['emoji']['from'] !== $compareData['emoji']['to']
			|| $compareData['description']['from'] !== $compareData['description']['to'];

		return $compareData;
	}

	/**
	 * @param int $tableId
	 * @param array $columns
	 * @param array $views
	 * @param array $columnOrder
	 * @param array $sort
	 * @param string|null $userId
	 *
	 * @return Table
	 *
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 * @throws BadRequestError
	 */
	public function updateTableStructure(int $tableId, array $columns, array $views, array $columnOrder = [], array $sort = [], ?string $userId = null): Table {
		$userId = $this->permissionsService->preCheckUserId($userId);

		// Check if the user has permission to manage the table
		$table = $this->find($tableId, true);
		if (!$this->permissionsService->canManageTable($table, $userId)) {
			throw new PermissionError('PermissionError: can not manage table with id ' . $tableId);
		}

		// Validate the structure of the columns and views arrays
		if (!isset($columns['addColumns']) || !is_array($columns['addColumns'])
			|| !isset($columns['removeColumns']) || !is_array($columns['removeColumns'])
			|| !isset($columns['modifyColumns']) || !is_array($columns['modifyColumns'])) {
			throw new BadRequestError('Invalid columns structure provided.');
		}

		if (!isset($views['addViews']) || !is_array($views['addViews'])
			|| !isset($views['removeViews']) || !is_array($views['removeViews'])
			|| !isset($views['modifyViews']) || !is_array($views['modifyViews'])) {
			throw new BadRequestError('Invalid views structure provided.');
		}

		// Add new columns
		foreach ($columns['addColumns'] as $columnData) {
			$this->columnService->importColumn($table, $columnData);
		}

		// Remove columns
		foreach ($columns['removeColumns'] as $columnData) {
			$removeColumn = $this->columnService->find($columnData['id'], $userId);
			if ($removeColumn->getTableId() !== $tableId) {
				throw new BadRequestError('Column with id ' . $columnData['id'] . ' does not belong to table with id ' . $tableId);
			}
			$this->columnService->delete($columnData['id']);
		}

		// Update existing columns
		foreach ($columns['modifyColumns'] as $columnData) {
			$fromColumn = $columnData['from'];
			$toColumn = $columnData['to'];
			$updateColumn = $this->columnService->find($fromColumn['id'], $userId);
			if ($updateColumn->getTableId() !== $tableId) {
				throw new BadRequestError('Column with id ' . $fromColumn['id'] . ' does not belong to table with id ' . $tableId);
			}
			$this->columnService->update($fromColumn['id'], $userId, ColumnDto::createFromArray($toColumn));
		}

		$updatedColumns = $this->columnService->findAllByTable($tableId, $userId, $table);
		$columnsMap = [];
		foreach ($updatedColumns as $column) {
			$columnsMap[$column->getUuid()] = $column;
		}

		// Update column order
		$columnSettings = ColumnSettings::createFromInputArray($columnOrder, $columnsMap);
		$table->setColumnOrder(\json_encode($columnSettings->jsonSerialize()));

		// Update sort rules
		$sortRuleSet = SortRuleSet::createFromInputArray($sort, $columnsMap);
		$table->setSort(\json_encode($sortRuleSet->jsonSerialize()));

		try {
			$table = $this->mapper->update($table);
		} catch (OcpDbException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(static::class . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// Add views
		foreach ($views['addViews'] as $view) {
			$view['columnSettings'] = ColumnSettings::createViewSettingsFromInputArray($view['columnSettings'], $columnsMap)->jsonSerialize();
			$view['sort'] = SortRuleSet::createFromInputArray($view['sort'], $columnsMap)->jsonSerialize();
			$view['filter'] = FilterSet::createFromInputArray($view['filter'], $columnsMap)->jsonSerialize();
			$this->viewService->importView($tableId, $view, $userId);
		}

		// Modify views
		foreach ($views['modifyViews'] as $item) {
			$fromView = $item['from'];
			$toView = $item['to'];
			$existingView = $this->viewService->find($fromView['id'], userId: $userId);
			$this->viewService->update($existingView->getId(), ViewUpdateInput::fromInputArray($toView, $columnsMap), $userId);
		}

		// Remove views
		foreach ($views['removeViews'] as $viewData) {
			$removeView = $this->viewService->find($viewData['id'], userId: $userId);
			if ($removeView->getTableId() !== $tableId) {
				throw new BadRequestError('View with id ' . $viewData['id'] . ' does not belong to table with id ' . $tableId);
			}
			$this->viewService->delete($viewData['id']);
		}

		return $table;
	}

	/**
	 * @param array $tableScheme
	 * @return void
	 * @throws BadRequestError
	 */
	public function validateTableScheme(array $tableScheme): void {
		if (!isset($tableScheme['columns']) || !is_array($tableScheme['columns'])) {
			throw new BadRequestError('Table scheme must include a valid columns array.');
		}
		if (!isset($tableScheme['views']) || !is_array($tableScheme['views'])) {
			throw new BadRequestError('Table scheme must include a valid views array.');
		}
		if (!isset($tableScheme['columnOrder']) || !is_array($tableScheme['columnOrder'])) {
			throw new BadRequestError('Table scheme must include a valid columnOrder array.');
		}
		if (!isset($tableScheme['sort']) || !is_array($tableScheme['sort'])) {
			throw new BadRequestError('Table scheme must include a valid sort array.');
		}
	}
}
