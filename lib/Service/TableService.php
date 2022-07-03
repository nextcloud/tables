<?php

namespace OCA\Tables\Service;

use DateTime;
use Exception;

use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Errors\PermissionError;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

use OCA\Tables\Db\Table;
use OCA\Tables\Db\TableMapper;
use Psr\Log\LoggerInterface;

class TableService extends SuperService {

	/** @var TableMapper */
	private $mapper;

    /** @var TableTemplateService */
    private $tableTemplateService;

    /** @var ColumnService */
    private $columnService;

    /** @var RowService */
    private $rowService;

    /** @var ShareService */
    private $shareService;

	public function __construct(PermissionsService $permissionsService, LoggerInterface $logger, $userId,
                                TableMapper $mapper, TableTemplateService $tableTemplateService, ColumnService $columnService, RowService $rowService, ShareService $shareService) {
        parent::__construct($logger, $userId, $permissionsService);
		$this->mapper = $mapper;
        $this->tableTemplateService = $tableTemplateService;
        $this->columnService = $columnService;
        $this->rowService = $rowService;
        $this->shareService = $shareService;
	}


    /**
     * @throws InternalError
     */
    public function findAll($userId = null): array {
        if($userId === null)
            $userId = $this->userId;

        try {
            $ownTables = $this->mapper->findAll($userId);
            $sharedTables = $this->shareService->findTablesSharedWithMe();

            // clean duplicates
            $newSharedTables = [];
            foreach ($sharedTables as $sharedTable) {
                $found = false;
                foreach ($ownTables as $ownTable) {
                    if ($sharedTable->getId() === $ownTable->getId()) {
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $newSharedTables[] = $sharedTable;
                }
            }
        } catch (\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
        return array_merge($ownTables, $newSharedTables);
    }


    /**
     * @throws PermissionError
     * @throws NotFoundError
     * @throws InternalError
     */
    public function find($id) {
		try {
			$table = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canReadTable($table))
                throw new PermissionError('PermissionError: can not read table with id '.$id);

            return $table;
        } catch (DoesNotExistException $e) {
            $this->logger->warning($e->getMessage());
            throw new NotFoundError($e->getMessage());
        } catch (MultipleObjectsReturnedException|\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }

    /**
     * @noinspection PhpUndefinedMethodInspection
     *
     * @throws \OCP\DB\Exception
     * @throws InternalError|PermissionError
     */
    public function create($title, $template) {
        $userId = $this->userId;
        $time = new DateTime();
		$item = new Table();
        $item->setTitle($title);
        $item->setOwnership($userId);
        $item->setCreatedBy($userId);
        $item->setLastEditBy($userId);
        $item->setCreatedAt($time->format('Y-m-d H:i:s'));
        $item->setLastEditAt($time->format('Y-m-d H:i:s'));
        try {
            $newTable = $this->mapper->insert($item);
        } catch (\OCP\DB\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
        if($template !== 'custom') {
            return $this->tableTemplateService->makeTemplate($newTable, $template);
        }
        return $newTable;
	}

    /**
     * @noinspection PhpUndefinedMethodInspection
     *
     * @throws InternalError
     */
    public function update($id, $title, $userId) {
		try {
            $item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canUpdateTable($item))
                throw new PermissionError('PermissionError: can not update table with id '.$id);

            $time = new DateTime();
            $item->setTitle($title);
            $item->setLastEditBy($userId);
            $item->setLastEditAt($time->format('Y-m-d H:i:s'));
			return $this->mapper->update($item);
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
		}
	}

    /**
     * @throws InternalError
     */
    public function delete($id, $userId = null) {
		try {
            $item = $this->mapper->find($id);

            // security
            if(!$this->permissionsService->canDeleteTable($item, $userId))
                throw new PermissionError('PermissionError: can not delete table with id '.$id);

            // delete all rows for that table
            $this->rowService->deleteAllByTable($id, $userId);

            // delete all columns for that table
            $columns = $this->columnService->findAllByTable($id);
            foreach ($columns as $column) {
                $this->columnService->delete($column->id, true, $userId);
            }

            // delete all shares for that table
            $this->shareService->deleteAllForTable($item);

            // delete table
			$this->mapper->delete($item);
			return $item;
		} catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new InternalError($e->getMessage());
        }
    }
}
