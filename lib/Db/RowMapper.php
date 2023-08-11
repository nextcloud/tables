<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Row> */
class RowMapper extends QBMapper {
	protected string $table = 'tables_rows';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, $this->table, Row::class);
	}

	/**
	 * @param int $id
	 *
	 * @return Row
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Row {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @param int $tableId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 */
	public function findAllByTable(int $tableId, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId)));

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	/**
	 * @param View $view
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 */
	public function findAllByView(View $view, ?int $limit = null, ?int $offset = null): array {
		return $this->findAllByTable($view->getTableId(),$limit,$offset);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function findNext(int $offsetId = -1): Row {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->gt('id', $qb->createNamedParameter($offsetId)))
			->setMaxResults(1)
			->orderBy('id', 'ASC');

		return $this->findEntity($qb);
	}

	/**
	 * @return int affected rows
	 * @throws Exception
	 */
	public function deleteAllByTable(int $tableId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('table_id', $qb->createNamedParameter($tableId))
			);
		return $qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function findAllWithColumn(int $columnId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table);

		if (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'postgres')) {
			// due to errors using doctrine with json, I paste the columnId inline.
			// columnId is a number, ensured by the parameter definition
			$qb->where('data::jsonb @> \'[{"columnId": ' . $columnId . '}]\'::jsonb');
		} elseif (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'sqlite')) {
			$qb->from($qb->createFunction('json_each(data)'));
			$qb->where('json_extract(value, "$.columnId") = :columnId');
		} else {
			$qb->where('JSON_CONTAINS(JSON_VALUE(data, \'$.columnId\'), :columnId, \'$\') = 1');
		}

		$qb->setParameter('columnId', $columnId);
		return $this->findEntities($qb);
	}

	/**
	 *
	 */
	public function countRows(int $tableId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'counter'));
		$qb->from($this->table);
		$qb->where(
			$qb->expr()->eq('table_id', $qb->createNamedParameter($tableId))
		);

		try {
			$result = $this->findOneQuery($qb);
			return (int)$result['counter'];
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return 0;
		}
	}
}
