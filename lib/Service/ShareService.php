<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use Psr\Log\LoggerInterface;

class ShareService extends SuperService {
	protected ShareMapper $mapper;

	protected TableMapper $tableMapper;

	protected UserHelper $userHelper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, string $userId,
	ShareMapper $shareMapper, TableMapper $tableMapper, UserHelper $userHelper) {
		parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $shareMapper;
		$this->tableMapper = $tableMapper;
		$this->userHelper = $userHelper;
	}


	/**
	 * @throws InternalError
	 *
	 * @psalm-param 'table' $nodeType
	 */
	public function findAll(string $nodeType, int $tableId): array {
		try {
			$shares = $this->mapper->findAllSharesForNode($nodeType, $tableId, $this->userId);
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
	 * @throws InternalError
	 */
	public function findTablesSharedWithMe(): array {
		$returnArray = [];
		try {
			// get all tables that are shared with me as user
			$tablesSharedWithMe = $this->mapper->findAllSharesFor('table', $this->userId);

			// get all tables that are shared with me by group
			$userGroups = $this->userHelper->getGroupsForUser($this->userId);
			foreach ($userGroups as $userGroup) {
				$shares = $this->mapper->findAllSharesFor('table', $userGroup->getDisplayName(), 'group');
				$tablesSharedWithMe = array_merge($tablesSharedWithMe, $shares);
			}
		} catch (\OCP\DB\Exception $e) {
			throw new InternalError($e->getMessage());
		}
		foreach ($tablesSharedWithMe as $share) {
			try {
				$table = $this->tableMapper->find($share->getNodeId());
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
			} catch (DoesNotExistException|\OCP\DB\Exception|MultipleObjectsReturnedException $e) {
				throw new InternalError($e->getMessage());
			}
			$returnArray[] = $table;
		}
		return $returnArray;
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

			$userId = $this->userId;
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

			return $this->addReceiverDisplayName($this->mapper->update($item));
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
	private function addReceiverDisplayName(Share $share):Share {
		$share->setReceiverDisplayName($this->userHelper->getUserDisplayName($share->getReceiver()));
		return $share;
	}

	private function addReceiverDisplayNames(array $shares): array {
		$return = [];
		foreach ($shares as $share) {
			$share->setReceiverDisplayName($this->userHelper->getUserDisplayName($share->getReceiver()));
			$return[] = $share;
		}
		return $return;
	}

	public function deleteAllForTable(Table $table):void {
		try {
			$this->mapper->deleteByNode($table->getId(), 'table');
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('something went wrong while deleting shares for table: '.$table->getId());
		}
	}
}
