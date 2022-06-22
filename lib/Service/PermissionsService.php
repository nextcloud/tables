<?php

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row;
use OCA\Tables\Db\Share;
use OCA\Tables\Db\ShareMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;


class PermissionsService extends SuperService {

    /** @var TableMapper */
    private $tableMapper;

    /** @var ShareMapper */
    private $shareMapper;

	public function __construct(LoggerInterface $logger, $userId, TableMapper $tableMapper, ShareMapper $shareMapper) {
        parent::__construct($logger, $userId);
        $this->tableMapper = $tableMapper;
        $this->shareMapper = $shareMapper;
	}


    public function canReadTable(?Table $table, $user = null): bool
    {
        if($table === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        return $this->userIsTableOwner($user, $table) || $this->userCanReadSharedTableByTableId($user, $table->getId());
    }

    public function canReadTableByTableId($tableId = null, $user = null): bool
    {
        if($tableId === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $tableId is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $tableId) || $this->userCanReadSharedTableByTableId($user, $tableId);
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    public function canUpdateTable(?Table $table, $user = null): bool
    {
        if($table === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        return $this->userIsTableOwner($user, $table);
    }

    public function canDeleteTable(?Table $table, $user = null): bool
    {
        if($table === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $table is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        return $this->userIsTableOwner($user, $table);
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function canReadRow(?Row $row, $user = null): bool
    {
        if($row === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $row is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $row->getTableId());
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function canReadRowsByTableId($tableId = null, $user = null): bool
    {
        if($tableId === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $tableId is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $tableId) || $this->userCanReadSharedTableByTableId($user, $tableId);
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    public function canCreateRowAtTableById($tableId = null, $user = null): bool
    {
        if($tableId === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $tableId is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $tableId);
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function canUpdateRow(?Row $row, $user = null): bool
    {
        if($row === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $row is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $row->getTableId());
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function canDeleteRow(?Row $row, $user = null): bool
    {
        if($row === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $row is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $row->getTableId());
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    public function canDeleteRowsByTableId(int $tableId = null, $user = null): bool
    {
        if($tableId === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $tableId is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $tableId);
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    public function canReadColumnsByTableId(int $tableId, $user = null): bool
    {
        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $tableId) || $this->userCanReadSharedTableByTableId($user, $tableId);
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function canReadColumn(?Column $column, $user = null): bool
    {
        if($column === null) {
            $this->logger->warning('try to verify permission: '.__FUNCTION__.' -> no $column is set');
            return false;
        }

        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $column->getTableId()) || $this->userCanReadSharedTableByTableId($user, $column->getTableId());
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    public function canCreateColumnAtTableById($tableId, $user = null): bool {
        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $tableId);
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function canUpdateColumn(Column $column, $user = null): bool {
        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $column->getTableId());
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    public function canDeleteColumn($column, $user = null): bool {
        if($user === null) {
            $user = $this->userId;
        }

        try {
            return $this->userIsTableOwnerByTableId($user, $column->getTableId());
        } catch (InternalError|NotFoundError $e) {
            return false;
        }
    }

    /**
     * @throws NotFoundError
     * @throws InternalError
     */
    private function userIsTableOwnerByTableId($user, $tableId): bool {
        try {
            $table = $this->tableMapper->find($tableId);
            return $this->userIsTableOwner($user, $table);
        } catch (DoesNotExistException $e) {
            throw new NotFoundError($e->getMessage());
        } catch (MultipleObjectsReturnedException|Exception $e) {
            throw new InternalError($e->getMessage());
        }
    }

    /** @noinspection PhpUndefinedMethodInspection */
    private function userIsTableOwner($user, Table $table): bool {
        return $table->getOwnership() === $user;
    }

    private function userCanReadSharedTableByTableId($user, $tableId): bool {
        try {
            $share = $this->shareMapper->findShareForNodeId($user, $tableId);
        } catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
            return false;
        }
        /** @noinspection PhpUndefinedMethodInspection */
        return !!$share->getPermissionRead();
    }

    public function canReadShare(Share $item): bool
    {
        // TODO
        return true;
    }

    public function canUpdateShare(Share $item): bool
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $item->getSender() === $this->userId;    }

    public function canDeleteShare(Share $item): bool
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $item->getSender() === $this->userId;
    }
}
