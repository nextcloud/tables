<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<RowSleeve> */
class RowSleeveMapper extends QBMapper {
	protected string $table = 'tables_row_sleeves';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, RowSleeve::class);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): RowSleeve {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $sleeveId
	 * @throws Exception
	 */
	public function deleteById(int $sleeveId) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($sleeveId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}
}
