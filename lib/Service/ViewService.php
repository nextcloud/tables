<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;


use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\UserHelper;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class ViewService extends SuperService {
	private ViewMapper $mapper;

	private TableMapper $tableMapper;

	private ShareService $shareService;

	private RowService $rowService;

	protected UserHelper $userHelper;

	protected IL10N $l;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		ViewMapper $mapper,
		TableMapper $tableMapper,
		ShareService $shareService,
		RowService $rowService,
		UserHelper $userHelper,
		IL10N $l
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->l = $l;
		$this->mapper = $mapper;
		$this->tableMapper = $tableMapper;
		$this->shareService = $shareService;
		$this->rowService = $rowService;
		$this->userHelper = $userHelper;
	}


	public function findAll(Table $table, ?string $userId = null): array {
		return $this->findAllGeneralised($table, true, $userId);
	}

	public function findAllNotBaseViews(Table $table, ?string $userId = null): array {
		return $this->findAllGeneralised($table, false, $userId);
	}

	private function findAllGeneralised(Table $table, bool $includeBaseView = true, ?string $userId = null): array {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			// security
			if (!$this->permissionsService->canReadElement($table, 'table', $userId)) {
				throw new PermissionError('PermissionError: can not read views for tableId '.$table->getId());
			}

			$allViews = $includeBaseView ? $allViews = $this->mapper->findAll($table->getId()) : $this->mapper->findAllNotBaseViews($table->getId());
			foreach ($allViews as $view) {
				$this->enhanceView($view, $userId);
			}
			return $allViews;
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->debug('permission error during looking for views', ['exception' => $e]);
			throw new PermissionError($e->getMessage());
		}
	}

	public function findBaseView(Table $table, bool $skipTableEnhancement = false, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$baseView = $this->mapper->findBaseView($table->getId());
			// security
			if (!$this->permissionsService->canReadElement($baseView, 'view', $userId)) {
				throw new PermissionError('PermissionError: can not read views for tableId '.$table->getId());
			}
			if(!$skipTableEnhancement) $this->enhanceView($baseView, $userId);
			return $baseView;
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		} catch (PermissionError $e) {
			$this->logger->debug('permission error during looking for views', ['exception' => $e]);
			throw new PermissionError($e->getMessage());
		}
	}

	/**
	 * @param int $id
	 * @param string|null $userId (null -> take from session, '' -> no user in context)
	 * @return View
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function find(int $id, bool $skipEnhancement = false, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$view = $this->mapper->find($id);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		// security

		if (!$this->permissionsService->canAccessView($view, $userId)) {
			throw new PermissionError('PermissionError: can not read view with id '.$id);
		}
		if(!$skipEnhancement) $this->enhanceView($view, $userId);

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
		$sharedViews = $this->shareService->findViewsSharedWithMe($userId);
		foreach ($sharedViews as $view) {
			$this->enhanceView($view, $userId);
		}
		return $sharedViews;
	}


	/**
	 * @param int $tableId
	 * @param string $title
	 * @param string|null $emoji
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function create(string $title, ?string $emoji, Table $table, bool $isBaseView = false, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId, false); // $userId is set

		// security
		if (!$this->permissionsService->canUpdateElement($table, 'table', $userId)) {
			throw new PermissionError('PermissionError: can not create view');
		}

		$time = new DateTime();
		$item = new View();
		$item->setTitle($title);
		if($emoji) {
			$item->setEmoji($emoji);
		}
		$item->setDescription('');
		$item->setIsBaseView($isBaseView);
		$item->setTableId($table->getId());
		$item->setCreatedBy($userId);
		$item->setLastEditBy($userId);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			$newItem = $this->mapper->insert($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		return $newItem;
	}


	/**
	 * @param int $id
	 * @param string $key
	 * @param string|null $value
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function updateSingle(int $id, string $key, ?string $value, ?string $userId = null): View {
		return $this->update($id, [$key => $value], $userId);
	}

	/**
	 * @param int $id
	 * @param array $data
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function update(int $id, array $data, bool $skipTableEnhancement = false, ?string $userId = null): View {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$view = $this->mapper->find($id, true);

			// security
			if (!$this->permissionsService->canManageElement($view, 'view', $userId)) {
				throw new PermissionError('PermissionError: can not update view with id '.$id);
			}

			foreach ($data as $key => $value) {
				$setterMethod = 'set'.ucfirst($key);
				$view->$setterMethod($value);
			}
			$time = new DateTime();
			$view->setLastEditBy($userId);
			$view->setLastEditAt($time->format('Y-m-d H:i:s'));
			$view = $this->mapper->update($view);
			if(!$skipTableEnhancement) $this->enhanceView($view, $userId);
			return $view;
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $id
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function delete(int $id, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId is set or ''

		try {
			$view = $this->mapper->find($id);
			if ($view->getIsBaseView()) {
				throw new InternalError('To delete the base view, delete the table instead');
			}

			// security
			if (!$this->permissionsService->canManageElement($view, 'view', $userId)) {
				throw new PermissionError('PermissionError: can not delete view with id '.$id);
			}
			$this->shareService->deleteAllForView($view);

			return $this->mapper->delete($view);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}



	/**
	 * @param int $id
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function deleteByObject(View $view, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId is set or ''

		try {
			// security
			if (!$this->permissionsService->canManageElement($view, 'view', $userId)) {
				throw new PermissionError('PermissionError: can not delete view with id '.$view->getId());
			}
			// delete all shares for that table
			$this->shareService->deleteAllForView($view);

			$this->mapper->delete($view);
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
	 *
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function enhanceView(View &$view, string $userId): void {
		// add owner display name for UI
		$view->setOwnerDisplayName($this->userHelper->getUserDisplayName($view->getOwnership()));

		// set if this is a shared table with you (somebody else shared it with you)
		// (senseless if we have no user in context)
		if ($userId !== '') {
			try {
				$share = $this->shareService->findViewShareIfSharedWithMe($view->getId());
				/** @noinspection PhpUndefinedMethodInspection */
				$view->setIsShared(true);
				$canManageTable = false;
				try {
					$manageTableShare = $this->shareService->findTableShareIfSharedWithMe($view->getTableId());
					$canManageTable = $manageTableShare->getPermissionManage();
				} catch (\Exception $e) {
				}
				/** @noinspection PhpUndefinedMethodInspection */
				$view->setOnSharePermissions([
					'read' => $share->getPermissionRead(),
					'create' => $share->getPermissionCreate(),
					'update' => $share->getPermissionUpdate(),
					'delete' => $share->getPermissionDelete(),
					'manage' => $share->getPermissionManage(),
					'manageTable' => $canManageTable,
				]);
			} catch (\Exception $e) {
			}
		}

		if (!$this->permissionsService->canReadElement($view, 'view', $userId)) return;

		// set hasShares if this table is shared by you (you share it with somebody else)
		// (senseless if we have no user in context)
		if ($userId !== '') {
			try {
				$allShares = $this->shareService->findAll('view', $view->getId());
				if ($view->getIsBaseView()) {
					$allShares = array_merge($allShares, $this->shareService->findAll('table', $view->getTableId()));
				}
				$view->setHasShares(count($allShares) !== 0);
			} catch (InternalError $e) {
			}
		}

		// add the rows count
		try {
			$view->setRowsCount($this->rowService->getViewRowsCount($view, $userId));
		} catch (InternalError|PermissionError $e) {
		}
	}

	/**
	 * @param Table $tableId
	 * @param null|string $userId
	 * @return int
	 * @throws PermissionError
	 * @throws \OCP\DB\Exception
	 */
	public function deleteAllByTable(Table $table, ?string $userId = null): View {
		// security
		/*if (!$this->permissionsService->canDeleteRowsByTableId($tableId, $userId)) {
			throw new PermissionError('delete all rows for table id = '.$tableId.' is not allowed.');
		} TODO: If you can delete a table you should be allowed to delete the views?!*/
		$views = $this->findAll($table,$userId);
		foreach ($views as $view) {
			if($view->getIsBaseView()) {
				$baseView = $view;
			} else {
				$this->deleteByObject($view, $userId);
			}
		}
		return $this->deleteByObject($baseView, $userId);
	}

	public function deleteColumnDataFromViews(int $columnId, Table $table) {
		$views = $this->mapper->findAll($table->getId());
		foreach ($views as $view) {
			$filteredSortingRules = array_filter($view->getSortArray(), function($sort) use ($columnId){
				return $sort['columnId'] !== $columnId;
			});
			$filteredSortingRules = array_values($filteredSortingRules);
			$filteredFilters = array_filter($view->getFilterArray(), function($filterGroup) use ($columnId){
				array_filter($filterGroup, function($filter) use ($columnId){
					return $filter['columnId'] !== $columnId;
				});
			});
			$data = [
				'columns' => json_encode(array_values(array_diff($view->getColumnsArray(), [$columnId]))),
				'sort' => json_encode($filteredSortingRules),
				'filter' => json_encode($filteredFilters),
			];

			$this->update($view->getId(), $data, $table);

			//TODODODOTODOTODOTODOTODOTOD
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
			$views = $this->mapper->search($term, $userId, $limit, $offset);
			foreach ($views as &$view) {
				/** @var string $userId */
				$this->enhanceView($view, $userId);
			}
			return $views;
		} catch (InternalError | \OCP\DB\Exception $e) {
			return [];
		}
	}
}
