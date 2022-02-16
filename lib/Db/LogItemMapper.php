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
     * @return Entity|Table
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     * @throws DoesNotExistException
     */
	public function find(int $id): Table {
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

    /**
     * @return array
     * @throws Exception
     */
	public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);
		return $this->findEntities($qb);
	}
}
