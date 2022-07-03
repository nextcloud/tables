<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;


class PermissionsService extends SuperService {

    /** @var TableMapper */
    private $tableMapper;

    /** @var ShareMapper */
    private $shareMapper;

    /** @var UserHelper */
    private $userHelper;

	public function __construct(LoggerInterface $logger, $userId, TableMapper $tableMapper, ShareMapper $shareMapper, UserHelper $userHelper) {
        parent::__construct($logger, $userId);
        $this->tableMapper = $tableMapper;
        $this->shareMapper = $shareMapper;
        $this->userHelper = $userHelper;
	}

    // ***** TABLES permissions *****

    public function canReadTable($table, string $userId = null): bool {
        try {
            $table = $this->getTableObject($table);
        } catch (InternalError $e) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }
        $userId = $userId ?: $this->userId;

        if($this->userIsTableOwner($userId, $table))
            return true;

        try {
            $this->getShareForTable($table, $userId);
            return true;
        } catch (InternalError|NotFoundError $e) {
        }

        return false;
    }

    public function canUpdateTable($table, string $userId = null): bool {
        try {
            $table = $this->getTableObject($table);
        } catch (InternalError $e) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }
        $userId = $userId ?: $this->userId;

        if($this->userIsTableOwner($userId, $table))
            return true;

        try {
            $share = $this->getShareForTable($table, $userId);
            /** @noinspection PhpUndefinedMethodInspection */
            return !!$share->getPermissionManage();
        } catch (InternalError|NotFoundError $e) {
        }

        return false;
    }

    public function canDeleteTable($table, string $userId = null): bool {
        try {
            $table = $this->getTableObject($table);
        } catch (InternalError $e) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }
        $userId = $userId ?: $this->userId;

        if($this->userIsTableOwner($userId, $table))
            return true;

        return false;
    }


    // ***** COLUMNS permissions *****

    public function canReadColumns($table, string $userId = null): bool {
        // if you can read the table, you also can read its columns
        return $this->canReadTable($table, $userId);
    }

    public function canCreateColumns($table, string $userId = null): bool {
        // this is the same permission as to update a table
        return $this->canUpdateTable($table, $userId);
    }

    public function canUpdateColumns($table, string $userId = null): bool {
        // this is the same permission as to update a table
        return $this->canUpdateTable($table, $userId);
    }

    public function canDeleteColumns($table, string $userId = null): bool {
        // this is the same permission as to update a table
        return $this->canUpdateTable($table, $userId);
    }


    // ***** ROWS permissions *****

    public function canReadRows($table, string $userId = null): bool {
        try {
            $table = $this->getTableObject($table);
        } catch (InternalError $e) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }
        $userId = $userId ?: $this->userId;

        if($this->userIsTableOwner($userId, $table))
            return true;

        try {
            $share = $this->getShareForTable($table, $userId);
            /** @noinspection PhpUndefinedMethodInspection */
            return !!$share->getPermissionRead() || !!$share->getPermissionManage();
        } catch (InternalError|NotFoundError $e) {
        }

        return false;
    }

    public function canCreateRows($table, string $userId = null): bool {
        try {
            $table = $this->getTableObject($table);
        } catch (InternalError $e) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }
        $userId = $userId ?: $this->userId;

        if($this->userIsTableOwner($userId, $table))
            return true;

        try {
            $share = $this->getShareForTable($table, $userId);
            /** @noinspection PhpUndefinedMethodInspection */
            return !!$share->getPermissionCreate() || !!$share->getPermissionManage();
        } catch (InternalError|NotFoundError $e) {
        }

        return false;
    }

    public function canUpdateRows($table, string $userId = null): bool {
        try {
            $table = $this->getTableObject($table);
        } catch (InternalError $e) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }
        $userId = $userId ?: $this->userId;

        if($this->userIsTableOwner($userId, $table))
            return true;

        try {
            $share = $this->getShareForTable($table, $userId);
            /** @noinspection PhpUndefinedMethodInspection */
            return !!$share->getPermissionUpdate() || !!$share->getPermissionManage();
        } catch (InternalError|NotFoundError $e) {
        }

        return false;
    }

    public function canDeleteRows($table, string $userId = null): bool {
        try {
            $table = $this->getTableObject($table);
        } catch (InternalError $e) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }
        $userId = $userId ?: $this->userId;

        if($this->userIsTableOwner($userId, $table))
            return true;

        try {
            $share = $this->getShareForTable($table, $userId);
            /** @noinspection PhpUndefinedMethodInspection */
            return !!$share->getPermissionDelete() || !!$share->getPermissionManage();
        } catch (InternalError|NotFoundError $e) {
        }

        return false;
    }


    // ***** SHARE permissions *****

    /** @noinspection PhpUndefinedMethodInspection */
    public function canReadShare(Share $share, string $userId = null): bool
    {
        $userId = $userId ?: $this->userId;

        if($share->getSender() === $userId) {
            return true;
        }

        if($share->getReceiverType() === 'user' && $share->getReceiver() === $userId) {
            return true;
        }

        if($share->getReceiverType() === 'group') {
            try {
                $userGroups = $this->userHelper->getGroupsForUser($userId);
                foreach ($userGroups as $userGroup) {
                    if($userGroup->getDisplayName() === $share->getReceiver()) {
                        return true;
                    }
                }
            } catch (InternalError $e) {
                return false;
            }
        }

        return false;
    }

    public function canUpdateShare(Share $item, string $userId = null): bool
    {
        $userId = $userId ?: $this->userId;

        /** @noinspection PhpUndefinedMethodInspection */
        return $item->getSender() === $userId;
    }

    public function canDeleteShare(Share $item, string $userId = null): bool
    {
        $userId = $userId ?: $this->userId;

        /** @noinspection PhpUndefinedMethodInspection */
        return $item->getSender() === $userId;
    }


    //  private methods ==========================================================================

    /**
     * give me a perfect object or a table id
     * I will respond with the perfect table object or an error
     *
     * @throws InternalError
     */
    private function getTableObject($table): Table {
        if(is_int($table)) {
            try {
                $table = $this->tableMapper->find($table);
            } catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
                $this->logger->error('could not get table object from db for: '.$table);
                $table = null;
            }
        }
        if($table instanceof Table) {
            return $table;
        } else {
            throw new InternalError('no table object: '.$table);
        }
    }

    /**
     * @throws NotFoundError
     * @throws InternalError
     */
    private function getShareForTable(Table $table, $userId): Share {
        // if shared by user
        try {
            return $this->shareMapper->findShareForNode($table->getId(), 'table', $userId, 'user');
        } catch (Exception $e) {
        }

        // if shared by group
        try {
            $userGroups = $this->userHelper->getGroupsForUser($userId);
        } catch (InternalError $e) {
            $this->logger->error('could not fetch user data for permission check');
            throw new InternalError('could not fetch user data for permission check');
        }
        foreach ($userGroups as $userGroup) {
            try {
                return $this->shareMapper->findShareForNode($table->getId(), 'table', $userGroup->getGID(), 'group');
            } catch (Exception $e) {
            }
        }
        throw new NotFoundError('no share found');
    }

    /** @noinspection PhpUndefinedMethodInspection */
    private function userIsTableOwner(string $userId, Table $table): bool {
        return $table->getOwnership() === $userId;
    }
}
