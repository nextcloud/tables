<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Db\Table;
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

	private ShareService $shareService;

	protected UserHelper $userHelper;

	protected IL10N $l;

	public function __construct(
		PermissionsService $permissionsService,
		LoggerInterface $logger,
		?string $userId,
		ViewMapper $mapper,
		ShareService $shareService,
		UserHelper $userHelper,
		IL10N $l
	) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->l = $l;
		$this->mapper = $mapper;
		$this->shareService = $shareService;
		$this->userHelper = $userHelper;
	}

	public function findBaseView(Table $table, bool $skipTableEnhancement = false, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			// security
			if (!$this->permissionsService->canReadViews($table, $userId)) {
				throw new PermissionError('PermissionError: can not read views for tableId '.$table->getId());
			}

			$baseView = $this->mapper->findBaseView($table->getId());
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
	 * Find all tables for a user
	 *
	 * takes the user from actual context or the given user
	 * it is possible to get all tables, but only if requested by cli
	 *
	 * @param int|null $tableId
	 * @param string|null $userId (null -> take from session, '' -> no user in context)
	 * @return array<View>
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function findAll(Table $table, ?string $userId = null): array {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			// security
			if (!$this->permissionsService->canReadViews($table, $userId)) {
				throw new PermissionError('PermissionError: can not read views for tableId '.$table->getId());
			}

			$allViews = $this->mapper->findAll($table->getId());
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


	/**
	 * @param int $id
	 * @param string|null $userId (null -> take from session, '' -> no user in context)
	 * @return View
	 * @throws PermissionError
	 * @throws NotFoundError
	 * @throws InternalError
	 */
	public function find(int $id, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId can be set or ''

		try {
			$view = $this->mapper->find($id);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}

		// security
		if (!$this->permissionsService->canReadElement($view, 'view', $userId)) {
			throw new PermissionError('PermissionError: can not read view with id '.$id);
		}

		$this->enhanceView($view, $userId);

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
	public function updateSingle(int $id, string $key, ?string $value, Table $table, ?string $userId = null): View {
		return $this->update($id, [$key => $value], $table, $userId);
	}

	/**
	 * @param int $id
	 * @param array $data
	 * @param string|null $userId
	 * @return View
	 * @throws InternalError
	 */
	public function update(int $id, array $data, Table $table, bool $skipTableEnhancement = false, ?string $userId = null): View {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$view = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canUpdateElement($table, 'table', $userId)) {
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
	public function delete(int $id, Table $table, ?string $userId = null): View {
		/** @var string $userId */
		$userId = $this->permissionsService->preCheckUserId($userId); // $userId is set or ''

		try {
			$view = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canUpdateElement($table, 'table', $userId)) {
				throw new PermissionError('PermissionError: can not delete view with id '.$id);
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
		$view->setOwnership($view->getCreatedBy()); //TODO
		// add owner display name for UI
		$this->addOwnerDisplayName($view); //TODO: ?

		// set hasShares if this table is shared by you (you share it with somebody else)
		// (senseless if we have no user in context)
		if ($userId !== '') {
			try {
				$shares = $this->shareService->findAll('view', $view->getId());
				$view->setHasShares(count($shares) !== 0);
			} catch (InternalError $e) {
			}
		}

		// add the rows count
		//try {
		//	$table->setRowsCount($this->rowService->getRowsCount($table->getId()));
		//} catch (InternalError|PermissionError $e) {
		$view->setRowsCount(0);	//TODO
		//}

		// set if this is a shared table with you (somebody else shared it with you)
		// (senseless if we have no user in context)
		if ($userId !== '') {
			try {
				$share = $this->shareService->findViewShareIfSharedWithMe($view->getId());
				/** @noinspection PhpUndefinedMethodInspection */
				$view->setIsShared(true);
				/** @noinspection PhpUndefinedMethodInspection */
				$view->setOnSharePermissions([
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

	private function addOwnerDisplayName(View $view): View {
		$view->setOwnerDisplayName($this->userHelper->getUserDisplayName($view->getOwnership()));
		return $view;
	}
}
