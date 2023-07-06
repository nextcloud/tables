<?php

namespace OCA\Tables\Db;

use OCA\Tables\Db\ColumnTypes\DatetimeColumnQB;
use OCA\Tables\Db\ColumnTypes\IColumnTypeQB;
use OCA\Tables\Db\ColumnTypes\NumberColumnQB;
use OCA\Tables\Db\ColumnTypes\SelectionColumnQB;
use OCA\Tables\Db\ColumnTypes\SuperColumnQB;
use OCA\Tables\Db\ColumnTypes\TextColumnQB;
use OCA\Tables\Helper\UserHelper;
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
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<Row> */
class RowMapper extends QBMapper {
	protected string $table = 'tables_rows';
	protected TextColumnQB $textColumnQB;
	protected SelectionColumnQB $selectionColumnQB;
	protected NumberColumnQB $numberColumnQB;
	protected DatetimeColumnQB $datetimeColumnQB;
	protected SuperColumnQB $genericColumnQB;
	protected ColumnMapper $columnMapper;
	protected LoggerInterface $logger;
	protected UserHelper $userHelper;

	public function __construct(IDBConnection $db, LoggerInterface $logger, TextColumnQB $textColumnQB, SelectionColumnQB $selectionColumnQB, NumberColumnQB $numberColumnQB, DatetimeColumnQB $datetimeColumnQB, SuperColumnQB $columnQB, ColumnMapper $columnMapper, UserHelper $userHelper) {
		parent::__construct($db, $this->table, Row::class);
		$this->logger = $logger;
		$this->textColumnQB = $textColumnQB;
		$this->numberColumnQB = $numberColumnQB;
		$this->selectionColumnQB = $selectionColumnQB;
		$this->datetimeColumnQB= $datetimeColumnQB;
		$this->genericColumnQB = $columnQB;
		$this->columnMapper = $columnMapper;
		$this->userHelper = $userHelper;
		$this->setPlatform();
	}

	private function setPlatform() {
		if (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'postgres')) {
			$this->genericColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_PGSQL);
			$this->textColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_PGSQL);
			$this->numberColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_PGSQL);
			$this->selectionColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_PGSQL);
			$this->datetimeColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_PGSQL);
		} elseif (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'sqlite')) {
			$this->genericColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_SQLITE);
			$this->textColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_SQLITE);
			$this->numberColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_SQLITE);
			$this->selectionColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_SQLITE);
			$this->datetimeColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_SQLITE);
		} else {
			$this->genericColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_MYSQL);
			$this->textColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_MYSQL);
			$this->numberColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_MYSQL);
			$this->selectionColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_MYSQL);
			$this->datetimeColumnQB->setPlatform(IColumnTypeQB::DB_PLATFORM_MYSQL);
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

	private function buildFilterByColumnType(&$qb, array $filter, string $filterId): string {
		try {
			$qbClassName = 'OCA\Tables\Db\ColumnTypes\\';
			$type = explode("-", $filter['columnType'])[0];

			$qbClassName .= ucfirst($type).'ColumnQB';

			$qbClass = Server::get($qbClassName);
			return $qbClass->addWhereFilterExpression($qb, $filter, $filterId);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type query builder class not found');
		}
		return '';
	}

	private function getInnerFilterExpressions(&$qb, $filterGroup, int $groupIndex): array {
		$innerFilterExpressions = [];
		foreach ($filterGroup as $index=>$filter) {
			$innerFilterExpressions[] =  $this->buildFilterByColumnType($qb, $filter, $groupIndex.$index);
		}
		return $innerFilterExpressions;
	}

	private function getFilterGroups(&$qb, $filters): array {
		$filterGroups = [];
		foreach ($filters as $groupIndex=>$filterGroup) {
			$filterGroups[] = $qb->expr()->andX(...$this->getInnerFilterExpressions($qb, $filterGroup, $groupIndex));
		}
		return $filterGroups;
	}

	private function resolveSearchValue(string $unresolvedSearchValue, string $userId): string {
		switch (ltrim($unresolvedSearchValue, '@')) {
			case 'me': return $userId;
			case 'my-name': return $this->userHelper->getUserDisplayName($userId);
			case 'checked': return 'true';
			case 'unchecked': return 'false';
			case 'stars-0': return '0';
			case 'stars-1': return '1';
			case 'stars-2': return '2';
			case 'stars-3': return '3';
			case 'stars-4': return '4';
			case 'stars-5': return '5';
			case 'datetime-date-today': return date('Y-m-d');
			case 'datetime-date-start-of-year': return date('Y-01-01');
			case 'datetime-date-start-of-month': return date('Y-m-01');
			case 'datetime-date-start-of-week':
				$day = date('w');
				return date('m-d-Y', strtotime('-'.$day.' days'));
			case 'datetime-time-now': return date('H:i');
			case 'datetime-now': return date('Y-m-d H:i');
			default: return $unresolvedSearchValue;
		}
	}

	private function addOrderByRules(IQueryBuilder &$qb, $sortArray) {
		foreach ($sortArray as $index=>$sortRule) {
			$sortMode = $sortRule['mode'];
			if (!in_array($sortMode, ['ASC', 'DESC'])) {
				continue;
			}
			$sortColumnPlaceholder = 'sortColumn'.$index;
			$orderString = 'JSON_EXTRACT(data, CONCAT( JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(data, \'$[*].columnId\'), \'one\', :'.$sortColumnPlaceholder.')), \'.value\'))';
			if (str_starts_with($sortRule['columnType'],'number')) {
				$orderString = 'CAST('.$orderString.' as int)';
				//TODO: Better solution?
			}
			$qb->addOrderBy($qb->createFunction($orderString),$sortMode);
			$qb->setParameter($sortColumnPlaceholder,$sortRule['columnId'], $qb::PARAM_INT);
		}
	}

	/**
	 *
	 */
	public function countRowsForBaseView(View $view): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'counter'));
		$qb->from($this->table);
		$qb->where(
			$qb->expr()->eq('table_id', $qb->createNamedParameter($view->getTableId()))
		);

		try {
			$result = $this->findOneQuery($qb);
			return (int)$result['counter'];
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return 0;
		}
	}

	/**
	 *
	 */
	public function countRowsForNotBaseView(View $view, $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'counter'))
			->from($this->table)
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($view->getTableId(), $qb::PARAM_INT)));

		$neededColumnIds = $this->getAllColumnIdsFromView($view);
		$neededColumns = $this->columnMapper->getColumnTypes($neededColumnIds);

		// Filter

		$this->addFilterToQuery($qb, $view, $neededColumns, $userId);

		try {
			$result = $this->findOneQuery($qb);
			return (int)$result['counter'];
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			return 0;
		}
	}


	private function addFilterToQuery(IQueryBuilder &$qb, View $view, array $neededColumns, string $userId): void {
		$enrichedFilters = $view->getFilterArray();
		if (count($enrichedFilters) > 0) {
			foreach ($enrichedFilters as &$filterGroup) {
				foreach ($filterGroup as &$filter) {
					$filter['columnType'] = $neededColumns[$filter['columnId']];
					if(str_starts_with($filter['value'], '@')) {
						$filter['value'] = $this->resolveSearchValue($filter['value'], $userId);
					}
				}
			}
			$qb->andWhere(
				$qb->expr()->orX(
					...$this->getFilterGroups($qb, $enrichedFilters)
				)
			);
		}
	}

	/**
	 * @param View $viewrows
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 */
	public function findAllByView(View $view, string $userId, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->table)
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($view->getTableId(), $qb::PARAM_INT)));


		$neededColumnIds = $this->getAllColumnIdsFromView($view);
		$neededColumns = $this->columnMapper->getColumnTypes($neededColumnIds);

		// Filter

		$this->addFilterToQuery($qb, $view, $neededColumns, $userId);

		// Sorting

		$enrichedSort = $view->getSortArray();
		foreach ($enrichedSort as &$sort) {
			$sort['columnType'] = $neededColumns[$sort['columnId']];
		}
		$this->addOrderByRules($qb, $enrichedSort);

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
		$sorts = $view->getSortArray();
		foreach ($filters as $filterGroup) {
			foreach ($filterGroup as $filter) {
				$neededColumnIds[] = $filter['columnId'];
			}
		}
		foreach ($sorts as $sortRule) {
			$neededColumnIds[] = $sortRule['columnId'];
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
