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

    /** @var ShareMapper */
    protected $mapper;

    /** @var TableMapper */
    protected $tableMapper;

    /** @var UserHelper */
    protected $userHelper;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, $userId,
    ShareMapper $shareMapper, TableMapper $tableMapper, UserHelper $userHelper) {
        parent::__construct($logger, $userId, $permissionsService);
        $this->mapper = $shareMapper;
        $this->tableMapper = $tableMapper;
        $this->userHelper = $userHelper;
	}


    /**
     * @throws InternalError
     */
    public function findAll($nodeType, int $tableId): array {
        try {
            $shares = $this->mapper->findAllSharesForNode($nodeType, $tableId, $this->userId);
            return $this->addReceiverDisplayName($shares);
        } catch (\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }


    /**
     * @throws PermissionError
     * @throws NotFoundError
     * @throws InternalError
     */
    public function find($id) {
		try {
			$item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canReadShare($item))
                throw new PermissionError('PermissionError: can not read share with id '.$id);

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
    public function findTablesSharedWithMe(): array
    {
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
                    'read'  => $share->getPermissionRead(),
                    'create'  => $share->getPermissionCreate(),
                    'update'  => $share->getPermissionUpdate(),
                    'delete'  => $share->getPermissionDelete(),
                    'manage'  => $share->getPermissionManage(),
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
     * @throws \OCP\DB\Exception
     * @throws InternalError
     */
    public function create($nodeId, $nodeType, $receiver, $receiverType, $permissionRead, $permissionCreate, $permissionUpdate, $permissionDelete, $permissionManage) {
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
     * @throws InternalError
     */
    public function updatePermission($id, $permission, $value) {
		try {
            $item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canUpdateShare($item))
                throw new PermissionError('PermissionError: can not update share with id '.$id);

            $userId = $this->userId;
            $time = new DateTime();

            if($permission === "read")
                $item->setPermissionRead($value);

            if($permission === "create")
                $item->setPermissionCreate($value);

            if($permission === "update")
                $item->setPermissionUpdate($value);

            if($permission === "delete")
                $item->setPermissionDelete($value);

            if($permission === "manage")
                $item->setPermissionManage($value);

            $item->setLastEditAt($time->format('Y-m-d H:i:s'));

			return $this->addReceiverDisplayName($this->mapper->update($item));
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
		}
	}

    /**
     * @throws InternalError
     */
    public function delete($id) {
		try {
            $item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canDeleteShare($item))
                throw new PermissionError('PermissionError: can not delete share with id '.$id);

			$this->mapper->delete($item);
            return $this->addReceiverDisplayName($item);
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }

    private function addReceiverDisplayName($shares) {
        if(!$shares && !is_array($shares)) {
            $this->logger->error("Try to load receiverDisplayName, but no share is given");
            return "";
        }

        if(is_array($shares)){
            $return = [];
            foreach ($shares as $share) {
                $share->setReceiverDisplayName($this->userHelper->getUserDisplayName($share->getReceiver()));
                $return[] = $share;
            }
            return $return;
        } else {
            $shares->setReceiverDisplayName($this->userHelper->getUserDisplayName($shares->getReceiver()));
            return $shares;
        }
    }
}
