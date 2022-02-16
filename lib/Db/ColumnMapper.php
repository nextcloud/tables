<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ColumnMapper extends QBMapper {
    protected $table = 'tables_columns';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Column::class);
	}

    /**
     * @param int $id
     * @return Entity|Table
     * @throws MultipleObjectsReturnedException
     * @throws Exception
     * @throws DoesNotExistException
     */
	public function find(int $id): Column {
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

    /**
     * @param integer $tableID
     * @return array
     * @throws Exception
     */
	public function findAllByTable(int $tableID): array {
        $qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableID)));
		return $this->findEntities($qb);
	}
}
