<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class RowMapper extends QBMapper {
    protected $table = 'tables_rows';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Row::class);
	}

    /**
     * @param int $id
     * @return Entity|Table
     * @throws DoesNotExistException
     * @throws Exception
     * @throws MultipleObjectsReturnedException
     */
	public function find(int $id): Row {
        // TODO check if request is permitted
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

    /**
     * @param int $tableId
     * @return array
     * @throws Exception
     */
	public function findAllByTable(int $tableId): array {
        // TODO check if request is permitted
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId)));
		return $this->findEntities($qb);
	}
}
