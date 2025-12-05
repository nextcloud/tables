<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @noinspection DuplicatedCode */

namespace OCA\Tables\Service;

use DateTime;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use OCA\Tables\AppInfo\Application;
use OCA\Tables\Constants\ViewUpdatableParameters;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Event\ViewDeletedEvent;
use OCA\Tables\Helper\UserHelper;
use OCA\Tables\Model\ColumnSettings;
use OCA\Tables\Model\FilterSet;
use OCA\Tables\Model\Permissions;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\Model\ViewUpdateInput;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesView from ResponseDefinitions
 */
class ViewService extends SuperService {
	private ViewMapper $mapper;

	private ShareService $shareService;

	private RowService $rowService;

	protected UserHelper $userHelper;

	protected FavoritesService $favoritesService;

	protected IL10N $l;
	private ContextService $contextService;

	protected IEventDispatcher $eventDispatcher;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		ViewMapper $mapper,
		ShareService $shareService,
		RowService $rowService,
		UserHelper $userHelper,
		FavoritesService $favoritesService,
		IEventDispatcher $eventDispatcher,
		ContextService $contextService,
		IL10N $l,
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->l = $l;
		$this->mapper = $mapper;
		$this->shareService = $shareService;
		$this->rowService = $rowService;
		$this->userHelper = $userHelper;
		$this->favoritesService = $favoritesService;
		$this->eventDispatcher = $eventDispatcher;
		$this->contextService = $contextService;
	}

	/**
	 * @param Table $table
	 * @param string|null $userId
	 * @return array
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function findAll(Table $table, ?string $userId = null): array {
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			// security
			if (!$this->permissionsService->canManageTable($table, $userId)) {
				throw new PermissionError('PermissionError: can not read views for tableId ' . $table->getId());
			}

			$allViews = $this->mapper->findAll($table->getId());
			foreach ($allViews as $view) {
				$this->enhanceView($view, $userId);
			}
			return $allViews;
		} catch (\OCP\DB\Exception|InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->debug('permission error during looking for views', ['exception' => $e]);
			throw new PermissionError($e->getMessage());
		}
	}

	/**
	 * @param View[] $items
	 * @return TablesView[]
	 */
	public function formatViews(array $items): array {
		return array_map(fn (View $item) => $item->jsonSerialize(), $items);
	}

	/**
	 * @param int $id
	 * @param bool $skipEnhancement
	 * @param string|null $userId (null -> take from session, '' -> no user in context)
	 * @return View
	 * @throws InternalError
	 * @throws PermissionError
	 * @throws NotFoundError
	 */
	public function find(int $id, bool $skipEnhancement = false, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$view = $this->mapper->find($id);
		} catch (InternalError|\OCP\DB\Exception|MultipleObjectsReturnedException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security

		if (!$this->permissionsService->canAccessView($view, $userId)) {
			throw new PermissionError('PermissionError: can not read view with id ' . $id);
		}
		if (!$skipEnhancement) {
			$this->enhanceView($view, $userId);
		}

		return $view;
	}

	/**
	 * @throws InternalError
	 */
	public function findSharedViewsWithMe(?string $userId = null): array {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''
		if ($userId === '') {
			return [];
		}

		$allViews = [];

		$sharedViews = $this->shareService->findViewsSharedWithMe($userId);
		foreach ($sharedViews as $sharedView) {
			$allViews[$sharedView->getId()] = $sharedView;
		}

		$contexts = $this->contextService->findAll($userId);
		foreach ($contexts as $context) {
			$nodes = $context->getNodes();
			foreach ($nodes as $node) {
				if ($node['node_type'] !== Application::NODE_TYPE_VIEW
					|| isset($allViews[$node['node_id']])
				) {
					continue;
				}
				$allViews[$node['node_id']] = $this->find($node['node_id'], false, $userId);
			}
		}

		foreach ($allViews as $view) {
			$this->enhanceView($view, $userId);
		}
		return array_values($allViews);
	}


	/**
	 * @param string $title
	 * @param string|null $emoji
	 * @param Table $table
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function create(string $title, ?string $emoji, Table $table, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId, false); // $userId is set

		// security
		if (!$this->permissionsService->canManageTable($table, $userId)) {
			throw new PermissionError('PermissionError: can not create view');
		}

		$time = new DateTime();
		$item = new View();
		$item->setTitle($title);
		if ($emoji) {
			$item->setEmoji($emoji);
		}
		$item->setDescription('');
		$item->setTableId($table->getId());
		$item->setCreatedBy($userId);
		$item->setLastEditBy($userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		// ownership is not stored with the record, but it might be necessary upon
		// further interaction with the view in the running process, as the instance
		// is cached now. The ownership is always inherited from the table.
		$item->setOwnership($table->getOwnership());
		try {
			$newItem = $this->mapper->insert($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		return $newItem;
	}

	/**
	 * @throws InternalError
	 * @throws PermissionError
	 * @throws InvalidArgumentException
	 */
	public function update(int $id, ViewUpdateInput $data, ?string $userId = null, bool $skipTableEnhancement = false): View {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$view = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canManageView($view, $userId)) {
				throw new PermissionError('PermissionError: can not update view with id ' . $id);
			}

			foreach ($data->updateDetail() as $parameter => $value) {
				if ($parameter === ViewUpdatableParameters::COLUMN_SETTINGS
					&& $value instanceof ColumnSettings
				) {
					$this->assertInputColumnsAreValid($view, $userId, $value);
				}

				if ($value instanceof JsonSerializable) {
					$insertableValue = json_encode($value);
				}

				$setterMethod = 'set' . ucfirst($parameter->value);
				$view->$setterMethod($insertableValue ?? $value);
			}

			$time = new DateTime();
			$view->setLastEditBy($userId);
			$view->setLastEditAt($time->format('Y-m-d H:i:s'));
			$view = $this->mapper->update($view);
			if (!$skipTableEnhancement) {
				$this->enhanceView($view, $userId);
			}
			return $view;
		} catch (InvalidArgumentException $e) {
			throw $e;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws PermissionError
	 */
	protected function assertInputColumnsAreValid(View $view, string $userId, ColumnSettings $columnSettings): void {
		$columnService = \OCP\Server::get(ColumnService::class);
		$availableColumns = $columnService->findAllByManagedView($view, $userId);
		$availableColumnIds = array_map(static fn (Column $column) => $column->getId(), $availableColumns);

		foreach ($columnSettings->columnInformation() as $columnInfo) {
			if (!in_array($columnInfo->getId(), $availableColumnIds, true)
				&& !Column::isValidMetaTypeId($columnInfo->getId())
			) {
				throw new InvalidArgumentException('Invalid column ID provided: ' . $columnInfo->getId());
			}
		}
	}

	/**
	 * @param int $id
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 * @throws PermissionError
	 * @throws NotFoundError
	 */
	public function delete(int $id, ?string $userId = null): View {
		$userId = $this->permissionsService->preCheckUserId($userId); // assume $userId is set or ''

		try {
			$view = $this->mapper->find($id);
		} catch (InternalError|MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// security
		if (!$this->permissionsService->canManageView($view, $userId)) {
			throw new PermissionError('PermissionError: can not delete view with id ' . $id);
		}
		$this->shareService->deleteAllForView($view);

		// delete node relations if view is in any context
		$this->contextService->deleteNodeRel($id, Application::NODE_TYPE_VIEW);

		try {
			$deletedView = $this->mapper->delete($view);

			$event = new ViewDeletedEvent(view: $view);

			$this->eventDispatcher->dispatchTyped($event);

			return $deletedView;
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
	}


	/**
	 * @param View $view
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function deleteByObject(View $view, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId is set or ''

		try {
			// security
			if (!$this->permissionsService->canManageView($view, $userId)) {
				throw new PermissionError('PermissionError: can not delete view with id ' . $view->getId());
			}
			// delete all shares for that table
			$this->shareService->deleteAllForView($view);

			// delete node relations if view is in any context
			$this->contextService->deleteNodeRel($view->getId(), Application::NODE_TYPE_VIEW);

			$this->mapper->delete($view);

			$event = new ViewDeletedEvent(view: $view);

			$this->eventDispatcher->dispatchTyped($event);

			return $view;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * add some basic values related to this view in context
	 *
	 * $userId can be set or ''
	 */
	private function enhanceView(View $view, string $userId): void {
		// add owner display name for UI
		$view->setOwnerDisplayName($this->userHelper->getUserDisplayName($view->getOwnership()));

		$this->setIsSharedState($view, $userId);

		if (!$this->permissionsService->canReadRowsByElement($view, 'view', $userId)) {
			return;
		}
		// add the rows count
		try {
			$view->setRowsCount($this->rowService->getViewRowsCount($view, $userId));
		} catch (InternalError|PermissionError $e) {
		}

		// Remove detailed view filtering and sorting information if necessary
		if ($view->getIsShared() && !$view->getOnSharePermissions()->manageTable) {
			$rawFilterArray = $view->getFilterArray();
			if ($rawFilterArray) {
				$view->setFilterArray(
					array_map(static function ($filterGroup) {
						// Instead of filter just indicate that there is a filter, but hide details
						return array_map(null, $filterGroup);
					},
						$rawFilterArray));
			}
			$rawSortArray = $view->getSortArray();
			if ($rawSortArray) {
				$view->setSortArray(
					array_map(static function (array $sortRule) use ($view): array {
						if (isset($sortRule['columnId'])
							&& (
								Column::isValidMetaTypeId($sortRule['columnId'])
								|| in_array($sortRule['columnId'], $view->getColumnIds(), true)
							)
						) {
							return $sortRule;
						}
						// Instead of sort rule just indicate that there is a rule, but hide details
						return [];
					},
						$rawSortArray));
			}
		}

		if ($this->favoritesService->isFavorite(Application::NODE_TYPE_VIEW, $view->getId())) {
			$view->setFavorite(true);
		}
	}

	private function setIsSharedState(View $view, string $userId): void {
		// set if this is a shared table with you (somebody else shared it with you)
		// (senseless if we have no user in context)
		if ($userId !== '') {
			if ($userId !== $view->getOwnership()) {
				try {
					try {
						$permissions = $this->shareService->getSharedPermissionsIfSharedWithMe($view->getId(), 'view', $userId);
					} catch (NotFoundError) {
						$permissions = $this->permissionsService->getPermissionArrayForNodeFromContexts($view->getId(), 'view', $userId);
					}
					$view->setIsShared(true);
					try {
						try {
							$manageTableShare = $this->shareService->getSharedPermissionsIfSharedWithMe($view->getTableId(), 'table', $userId);
						} catch (NotFoundError) {
							$manageTableShare = $this->permissionsService->getPermissionArrayForNodeFromContexts($view->getTableId(), 'table', $userId);
						}
						if ($manageTableShare->manage) {
							$permissions->manageTable = true;
						}
					} catch (NotFoundError $e) {
					} catch (\Exception $e) {
						throw new InternalError($e->getMessage());
					}
					$view->setOnSharePermissions($permissions);
				} catch (NotFoundError $e) {
				} catch (\Exception $e) {
					$this->logger->warning('Exception occurred while setting shared permissions: ' . $e->getMessage() . ' No permissions granted.');
					$view->setOnSharePermissions(new Permissions());
				}
			} else {
				// set hasShares if this table is shared by you (you share it with somebody else)
				// (senseless if we have no user in context)
				try {
					$allShares = $this->shareService->findAll('view', $view->getId());
					$view->setHasShares(count($allShares) !== 0);
				} catch (InternalError $e) {
				}
			}
		} else {
			$view->setIsShared($this->shareService->hasLinkShare($view));
			$view->setOnSharePermissions(new Permissions(read: true));
		}
	}

	/**
	 * @param Table $table
	 * @param null|string $userId
	 * @return void
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function deleteAllByTable(Table $table, ?string $userId = null): void {
		// security
		if (!$this->permissionsService->canManageTable($table, $userId)) {
			throw new PermissionError('delete all rows for table id = ' . $table->getId() . ' is not allowed.');
		}
		$views = $this->findAll($table, $userId);
		foreach ($views as $view) {
			$this->deleteByObject($view, $userId);
		}
	}

	/**
	 * @param int $columnId
	 * @param Table $table
	 * @return void
	 * @throws InternalError
	 * @throws PermissionError
	 */
	public function deleteColumnDataFromViews(int $columnId, Table $table): void {
		try {
			$views = $this->mapper->findAll($table->getId());
		} catch (\OCP\DB\Exception $e) {
			throw new InternalError($e->getMessage());
		}
		foreach ($views as $view) {
			$filteredSortingRules = array_filter($view->getSortArray(), static function (array $sort) use ($columnId) {
				return $sort['columnId'] !== $columnId;
			});
			$filteredSortingRules = array_values($filteredSortingRules);

			$applicableFilterArray = $this->removeColumnFromFilters($view->getFilterArray(), $columnId);

			$applicableViewColumnInformationRecords = array_filter(
				$view->getColumnsSettingsArray(),
				static function (ViewColumnInformation $viewColumnInformation) use ($columnId): bool {
					return $viewColumnInformation->getId() !== $columnId;
				}
			);

			$viewUpdateInput = new ViewUpdateInput(
				columnSettings: new ColumnSettings($applicableViewColumnInformationRecords),
				filterSet: FilterSet::createFromInputArray($applicableFilterArray),
				sortRuleSet: SortRuleSet::createFromInputArray($filteredSortingRules),
			);

			$this->update($view->getId(), $viewUpdateInput);
		}
	}

	protected function removeColumnFromFilters(array $originalFilterSetArray, int $columnId): array {
		$applicableFilterSetArray = [];
		foreach ($originalFilterSetArray as $filterGroupArray) {
			$currentGroup = [];
			foreach ($filterGroupArray as $filterArray) {
				if ($filterArray['columnId'] === $columnId) {
					continue;
				}
				$currentGroup[] = $filterArray;
			}
			$applicableFilterSetArray[] = $currentGroup;
		}
		return $applicableFilterSetArray;
	}

	/**
	 * @param string $term
	 * @param int $limit
	 * @param int $offset
	 * @param string|null $userId
	 * @return View[]
	 */
	public function search(string $term, int $limit = 100, int $offset = 0, ?string $userId = null): array {
		try {
			/** @var string $userId */
			$userId = $this->permissionsService->preCheckUserId($userId);
			$views = $this->mapper->search($term, $userId, $limit, $offset);
			foreach ($views as $view) {
				$this->enhanceView($view, $userId);
			}
			return $views;
		} catch (InternalError|\OCP\DB\Exception $e) {
			return [];
		}
	}

	/**
	 * Add a column to a view's settings
	 *
	 * @param View $view The view object
	 * @param Column $column The column object to add
	 * @param string|null $userId The user ID performing the action
	 * @return void
	 * @throws InternalError
	 */
	public function addColumnToView(View $view, Column $column, ?string $userId = null): void {
		try {
			$viewColumnInformation = $view->getColumnsSettingsArray();

			$orders = array_map(fn (ViewColumnInformation $setting) => $setting->getOrder(), $viewColumnInformation);
			$nextOrder = $orders ? max($orders) + 1 : 0;

			$viewColumnInformation[] = new ViewColumnInformation($column->getId(), $nextOrder);
			$columnSettings = new ColumnSettings($viewColumnInformation);

			$viewUpdate = new ViewUpdateInput(columnSettings: $columnSettings);
			$this->update($view->getId(), $viewUpdate, $userId, true);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $tableId
	 * @param array $view
	 * @param string $userId
	 *
	 * @return void
	 *
	 * @throws InternalError
	 */
	public function importView(int $tableId, array $view, string $userId): void {
		$item = new View();
		$item->setTableId($tableId);
		$item->setTitle($view['title']);
		$item->setEmoji($view['emoji']);
		$item->setCreatedBy($userId);
		$item->setCreatedAt($view['createdAt']);
		$item->setLastEditBy($userId);
		$item->setLastEditAt($view['lastEditAt']);
		$item->setDescription($view['description']);
		$item->setColumns(json_encode($view['columnSettings']));
		$item->setSort(json_encode($view['sort']));
		$item->setFilter(json_encode($view['filter']));
		try {
			$this->mapper->insert($item);
		} catch (\Exception $e) {
			$this->logger->error('userMigrationImport insert error: ' . $e->getMessage());
			throw new InternalError('userMigrationImport insert error: ' . $e->getMessage());
		}
	}
}
