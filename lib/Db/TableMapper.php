<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Table> */
class TableMapper extends QBMapper {
	protected string $table = 'tables_tables';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Table::class);
	}

	/**
	 * @param int $id
	 *
	 * @return Table
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Table {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws Exception
	 */
	public function findAll(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('ownership', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}
}
