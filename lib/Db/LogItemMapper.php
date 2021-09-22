<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class LogItemMapper extends QBMapper {
    protected $table = 'tables_log';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, LogItem::class);
	}

    /**
     * @param int $id
     * @param string $userId
     * @return Entity|Table
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     * @throws DoesNotExistException
     */
	public function find(int $id): Table {
        // TODO check if request is permitted
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

    /**
     * @param string|null $userId
     * @param int|null $tableId
     * @param int|null $rowId
     * @param int|null $columnId
     * @param string|null $payloadKeyword
     * @return array
     * @throws Exception
     */
	public function findAll(string $userId = null, int $tableId = null, int $rowId = null, int $columnId = null, string $payloadKeyword = null): array {
        // TODO check if request is permitted
        // TODO realize filtering
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);
		return $this->findEntities($qb);
	}
}
