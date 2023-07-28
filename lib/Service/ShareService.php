<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Db\View;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\GroupHelper;
use OCA\Tables\Helper\UserHelper;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

class ShareService extends SuperService {
	protected ShareMapper $mapper;

	protected TableMapper $tableMapper;

	protected ViewMapper $viewMapper;

	protected UserHelper $userHelper;

	protected GroupHelper $groupHelper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, ?string $userId,
		ShareMapper $shareMapper, TableMapper $tableMapper, ViewMapper $viewMapper, UserHelper $userHelper, GroupHelper $groupHelper) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $shareMapper;
		$this->tableMapper = $tableMapper;
		$this->viewMapper = $viewMapper;
		$this->userHelper = $userHelper;
		$this->groupHelper = $groupHelper;
	}


	/**
	 * @throws InternalError
	 *
	 * @psalm-param 'table' $nodeType
	 */
	public function findAll(string $nodeType, int $nodeId, ?string $userId = null): array {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			/** @var string $userId */
			$shares = $this->mapper->findAllSharesForNode($nodeType, $nodeId, $userId);
			return $this->addReceiverDisplayNames($shares);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}


	/**
	 * @param int $id
	 * @return Share
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function find(int $id):Share {
		try {
			$item = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canReadShare($item)) {
				throw new PermissionError('PermissionError: can not read share with id '.$id);
			}

			return $item;
		} catch (DoesNotExistException $e) {
			$this->logger->warning($e->getMessage());
			throw new NotFoundError($e->getMessage());
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param string|null $userId
	 * @return array
	 * @throws InternalError
	 */
	public function findViewsSharedWithMe(?string $userId = null): array {
		return $this->findElementsSharedWithMe('view', $userId);
	}

	/**
	 * @param string|null $userId
	 * @return array
	 * @throws InternalError
	 */
	public function findTablesSharedWithMe(?string $userId = null): array {
		return $this->findElementsSharedWithMe('table', $userId);
	}

	/**
	 * @throws InternalError
	 */
	private function findElementsSharedWithMe(?string $elementType = 'table', ?string $userId = null): array {
		$userId = $this->permissionsService->preCheckUserId($userId);

		$returnArray = [];

		try {
			/** @var string $userId */
			// get all views or tables that are shared with me as user
			$elementsSharedWithMe = $this->mapper->findAllSharesFor($elementType, $userId, $userId);

			// get all views or tables that are shared with me by group
			$userGroups = $this->userHelper->getGroupsForUser($userId);
			foreach ($userGroups as $userGroup) {
				$shares = $this->mapper->findAllSharesFor($elementType, $userId, $userGroup->getGid(), 'group');
				$elementsSharedWithMe = array_merge($elementsSharedWithMe, $shares);
			}
		} catch (\OCP\DB\Exception $e) {
			throw new InternalError($e->getMessage());
		}
		foreach ($elementsSharedWithMe as $share) {
			try {
				if ($elementType === 'table') {
					$element = $this->tableMapper->find($share->getNodeId());
				} elseif ($elementType === 'view') {
					$element = $this->viewMapper->find($share->getNodeId());
				} else {
					throw new InternalError('Cannot find element of type '.$elementType);
				}
				// Check if en element with this id is already in the result array
				$index = array_search($element->getId(), array_column($returnArray, 'id'));
				if (!$index) {
					$returnArray[] = $element;
				}
			} catch (DoesNotExistException|\OCP\DB\Exception|MultipleObjectsReturnedException $e) {
				throw new InternalError($e->getMessage());
			}
		}
		return $returnArray;
	}


	/**
	 * @param int $elementId
	 * @param string|null $elementType
	 * @param string|null $userId
	 * @return array
	 * @throws InternalError
	 * @throws NotFoundError
	 */
	public function getSharedPermissionsIfSharedWithMe(int $elementId, ?string $elementType = 'table', ?string $userId = null): array {
		$userId = $this->permissionsService->preCheckUserId($userId);
		return $this->permissionsService->getSharedPermissionsIfSharedWithMe($elementId, $elementType, $userId);
	}












	/**
	 * @noinspection PhpUndefinedMethodInspection
	 *
	 * @param int $nodeId
	 * @param string $nodeType
	 * @param string $receiver
	 * @param string $receiverType
	 * @param bool $permissionRead
	 * @param bool $permissionCreate
	 * @param bool $permissionUpdate
	 * @param bool $permissionDelete
	 * @param bool $permissionManage
	 * @return Share
	 * @throws InternalError
	 */
	public function create(int $nodeId, string $nodeType, string $receiver, string $receiverType, bool $permissionRead, bool $permissionCreate, bool $permissionUpdate, bool $permissionDelete, bool $permissionManage):Share {
		$time = new DateTime();
		$item = new Share();
		$item->setSender($this->userId);
		$item->setReceiver($receiver);
		$item->setReceiverType($receiverType);
		$item->setNodeId($nodeId);
		$item->setNodeType($nodeType);
		$item->setPermissionRead($permissionRead);
		$item->setPermissionCreate($permissionCreate);
		$item->setPermissionUpdate($permissionUpdate);
		$item->setPermissionDelete($permissionDelete);
		$item->setPermissionManage($permissionManage);
		$item->setCreatedAt($time->format('Y-m-d H:i:s'));
		$item->setLastEditAt($time->format('Y-m-d H:i:s'));
		try {
			$newShare = $this->mapper->insert($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
		return $this->addReceiverDisplayName($newShare);
	}

	/**
	 * @noinspection PhpUndefinedMethodInspection
	 *
	 * @param int $id
	 * @param string $permission
	 * @param bool $value
	 * @return Share
	 * @throws InternalError
	 */
	public function updatePermission(int $id, string $permission, bool $value): Share {
		try {
			$item = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canUpdateShare($item)) {
				throw new PermissionError('PermissionError: can not update share with id '.$id);
			}

			$time = new DateTime();

			if ($permission === "read") {
				$item->setPermissionRead($value);
			}

			if ($permission === "create") {
				$item->setPermissionCreate($value);
			}

			if ($permission === "update") {
				$item->setPermissionUpdate($value);
			}

			if ($permission === "delete") {
				$item->setPermissionDelete($value);
			}

			if ($permission === "manage") {
				$item->setPermissionManage($value);
			}

			$item->setLastEditAt($time->format('Y-m-d H:i:s'));

			$share = $this->mapper->update($item);
			return $this->addReceiverDisplayName($share);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param int $id
	 * @return Share
	 * @throws InternalError
	 */
	public function delete(int $id): Share {
		try {
			$item = $this->mapper->find($id);

			// security
			if (!$this->permissionsService->canDeleteShare($item)) {
				throw new PermissionError('PermissionError: can not delete share with id '.$id);
			}

			$this->mapper->delete($item);
			return $this->addReceiverDisplayName($item);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param Share $share
	 * @return Share
	 * @noinspection PhpUndefinedMethodInspection
	 */
	private function addReceiverDisplayName(Share &$share):Share {
		if ($share->getReceiverType() === 'user') {
			$share->setReceiverDisplayName($this->userHelper->getUserDisplayName($share->getReceiver()));
		} elseif ($share->getReceiverType() === 'group') {
			$share->setReceiverDisplayName($this->groupHelper->getGroupDisplayName($share->getReceiver()));
		} else {
			$this->logger->info('can not use receiver type to get display name');
			$share->setReceiverDisplayName($share->getReceiver());
		}
		return $share;
	}

	private function addReceiverDisplayNames(array $shares): array {
		foreach ($shares as $share) {
			$this->addReceiverDisplayName($share);
		}
		return $shares;
	}

	public function deleteAllForTable(Table $table):void {
		try {
			$this->mapper->deleteByNode($table->getId(), 'table');
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('something went wrong while deleting shares for table: '.$table->getId());
		}
	}

	public function deleteAllForView(View $view):void {
		try {
			$this->mapper->deleteByNode($view->getId(), 'view');
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('something went wrong while deleting shares for view: '.$view->getId());
		}
	}
}
