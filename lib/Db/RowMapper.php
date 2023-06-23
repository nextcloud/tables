<?php

namespace OCA\Tables\Db;

use OCA\Tables\DB\ColumnTypes\IColumnTypeQB;
use OCA\Tables\DB\ColumnTypes\SuperColumnQB;
use OCA\Tables\DB\ColumnTypes\TextColumnQB;
use OCA\Tables\Service\ColumnTypes\TextLineBusiness;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/** @template-extends QBMapper<Row> */
class RowMapper extends QBMapper {
	protected string $table = 'tables_rows';
	protected TextColumnQB $textColumnQB;
	protected SuperColumnQB $genericColumnQB;
	protected ColumnMapper $columnMapper;

	public function __construct(IDBConnection $db, TextColumnQB $textColumnQB, SuperColumnQB $columnQB, ColumnMapper $columnMapper) {
		parent::__construct($db, $this->table, Row::class);
		$this->textColumnQB = $textColumnQB;
		$this->genericColumnQB = $columnQB;
		$this->columnMapper = $columnMapper;
		$this->setPlatform();
	}

	private function setPlatform() {
		if (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'postgres')) {
			$this->genericColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_PGSQL);
			$this->textColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_PGSQL);
		} elseif (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'sqlite')) {
			$this->genericColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_SQLITE);
			$this->textColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_SQLITE);
		} else {
			$this->genericColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_MYSQL);
			$this->textColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_MYSQL);
		}
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

	private function getInnerFilterExpressions(): array {

	}

	/**
	 * @param View $view
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 */
	public function findAllByView(View $view, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($view->getTableId())));

		$neededColumnIds = $this->getAllColumnIdsFromView($view);
		$neededColumns = $this->columnMapper->getColumnTypes($neededColumnIds);

		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->eq('category1', ':category1'),
				$qb->expr()->eq('category2', ':category2'),
				$qb->expr()->eq('category3', ':category3')
			)

		try {
			$businessClassName = 'OCA\Tables\Service\ColumnTypes\\';
			/** @noinspection PhpUndefinedMethodInspection */
			$businessClassName .= ucfirst($column->getType()).ucfirst($column->getSubtype()).'Business';
			/** @var TextLineBusiness $columnBusiness */
			$columnBusiness = Server::get($businessClassName);
			return $columnBusiness->parseValue($value, $column);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type business class not found');
		}


		// how do we know what column type it is and which ColumnQB we have to use?
		$this->textColumnQB->addWhereForFindAllByView($qb, $view);

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		return $this->findEntities($qb);
	}

	private function getAllColumnIdsFromView(View $view): array {
		$neededColumnIds = [];
		$filters = $view->getFilterArray();
		foreach ($filters as $filterGroup) {
			foreach ($filterGroup as $filter) {
				$neededColumnIds[] = $filter['columnId'];
			}
		}
		// TODO add sorting ids
		return array_unique($neededColumnIds);
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

		$this->genericColumnQB->addWhereForFindAllWithColumn($qb, $columnId);

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
