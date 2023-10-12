<?php

/** @noinspection DuplicatedCode */

namespace OCA\Tables\Service;

use DateTime;

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

use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type TablesShare from ResponseDefinitions
 */
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
	 */
	public function findAll(string $nodeType, int $nodeId, ?string $userId = null, bool $enhanceShares = true): array {
		$userId = $this->permissionsService->preCheckUserId($userId);

		try {
			$shares = $this->mapper->findAllSharesForNode($nodeType, $nodeId, $userId);
			return $enhanceShares ? $this->addReceiverDisplayNames($shares) : $shares;
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage());
			throw new InternalError($e->getMessage());
		}
	}

	/**
	 * @param Share[] $items
	 * @return TablesShare[]
	 */
	public function formatShares(array $items): array {
		return array_map(fn (Share $item) => $item->jsonSerialize(), $items);
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
				$shares = $this->mapper->findAllSharesFor($elementType, $userGroup->getGid(), $userId, 'group');
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
	 * @throws NotFoundError
	 */
	public function getSharedPermissionsIfSharedWithMe(int $elementId, ?string $elementType = 'table', ?string $userId = null): array {
		try {
			$userId = $this->permissionsService->preCheckUserId($userId);
		} catch (InternalError $e) {
			$this->logger->warning('Could not pre check user: '.$e->getMessage().' Permission denied.');
			return [];
		}
		return $this->permissionsService->getSharedPermissionsIfSharedWithMe($elementId, $elementType, $userId);
	}

	/**
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
		// TODO Do we need to check if create is allowed for requested nodeId?!
		// should also return not found if share_node could not be found
		// should also return no permission if sender don't have permission to create a share for node
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
	 * @param int $id
	 * @param string $permission
	 * @param bool $value
	 * @return Share
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function updatePermission(int $id, string $permission, bool $value): Share {
		try {
			$item = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		// security
		if (!$this->permissionsService->canManageElementById($item->getNodeId(), $item->getNodeType())) {
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

		try {
			$share = $this->mapper->update($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		return $this->addReceiverDisplayName($share);
	}

	/**
	 * @param int $id
	 * @return Share
	 * @throws InternalError
	 * @throws NotFoundError
	 * @throws PermissionError
	 */
	public function delete(int $id): Share {
		try {
			$item = $this->mapper->find($id);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}

		// security
			if (!$this->permissionsService->canManageElementById($item->getNodeId(), $item->getNodeType())) {
				throw new PermissionError('PermissionError: can not delete share with id '.$id);
			}

		try {
			$this->mapper->delete($item);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		return $this->addReceiverDisplayName($item);
	}

	/**
	 * @param Share $share
	 * @return Share
	 */
	private function addReceiverDisplayName(Share $share):Share {
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

	/**
	 * @throws InternalError
	 * @return Share[]
	 */
	public function changeSenderForNode(string $nodeType, int $nodeId, string $newOwnerUserId, ?string $userId = null): array {
		$sharesForTable = $this->findAll($nodeType, $nodeId, $userId, false);
		$newShares = [];

		foreach ($sharesForTable as $share) {
			/* @var Share $share */
			$share->setSender($newOwnerUserId);
			try {
				$this->mapper->update($share);
			} catch (\OCP\DB\Exception $e) {
				$this->logger->warning("Could not update share to change the sender: ".$e->getMessage(), ['exception' => $e]);
				throw new InternalError("Could not update share to change the sender");
			}
			$newShares[] = $share;
		}
		return $newShares;
	}

}
