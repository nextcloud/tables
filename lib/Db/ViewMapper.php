<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<View> */
class ViewMapper extends QBMapper {
	protected string $table = 'tables_views';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, View::class);
	}

	/**
	 * @throws Exception
	 */
	public function find(int $id): View {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws Exception
	 */
	public function findAll(?int $tableId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);
		if ($tableId !== null) {
			$qb->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)));
		}
		return $this->findEntities($qb);
	}
}
