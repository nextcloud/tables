<?php

namespace OCA\Tables\Db;

use OCA\Tables\Db\ColumnTypes\DatetimeColumnQB;
use OCA\Tables\Db\ColumnTypes\IColumnTypeQB;
use OCA\Tables\Db\ColumnTypes\NumberColumnQB;
use OCA\Tables\Db\ColumnTypes\SelectionColumnQB;
use OCA\Tables\Db\ColumnTypes\SuperColumnQB;
use OCA\Tables\Db\ColumnTypes\TextColumnQB;
use OCA\Tables\Db\ColumnTypes\UsergroupColumnQB;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<LegacyRow> */
class LegacyRowMapper extends QBMapper {
	protected string $table = 'tables_rows';
	protected TextColumnQB $textColumnQB;
	protected SelectionColumnQB $selectionColumnQB;
	protected NumberColumnQB $numberColumnQB;
	protected DatetimeColumnQB $datetimeColumnQB;
	protected UsergroupColumnQB $usergroupColumnQB;
	protected SuperColumnQB $genericColumnQB;
	protected ColumnMapper $columnMapper;
	protected LoggerInterface $logger;
	protected UserHelper $userHelper;
	protected Row2Mapper $rowMapper;

	protected int $platform;

	public function __construct(
		IDBConnection $db,
		LoggerInterface $logger,
		TextColumnQB $textColumnQB,
		SelectionColumnQB $selectionColumnQB,
		NumberColumnQB $numberColumnQB,
		DatetimeColumnQB $datetimeColumnQB,
		UsergroupColumnQB $usergroupColumnQB,
		SuperColumnQB $columnQB,
		ColumnMapper $columnMapper,
		UserHelper $userHelper,
		Row2Mapper $rowMapper) {
		parent::__construct($db, $this->table, LegacyRow::class);
		$this->logger = $logger;
		$this->textColumnQB = $textColumnQB;
		$this->numberColumnQB = $numberColumnQB;
		$this->selectionColumnQB = $selectionColumnQB;
		$this->datetimeColumnQB = $datetimeColumnQB;
		$this->usergroupColumnQB = $usergroupColumnQB;
		$this->genericColumnQB = $columnQB;
		$this->columnMapper = $columnMapper;
		$this->userHelper = $userHelper;
		$this->rowMapper = $rowMapper;
		$this->setPlatform();
	}

	private function setPlatform() {
		if (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'postgres')) {
			$this->platform = IColumnTypeQB::DB_PLATFORM_PGSQL;
		} elseif (str_contains(strtolower(get_class($this->db->getDatabasePlatform())), 'sqlite')) {
			$this->platform = IColumnTypeQB::DB_PLATFORM_SQLITE;
		} else {
			$this->platform = IColumnTypeQB::DB_PLATFORM_MYSQL;
		}
		$this->genericColumnQB->setPlatform($this->platform);
		$this->textColumnQB->setPlatform($this->platform);
		$this->numberColumnQB->setPlatform($this->platform);
		$this->selectionColumnQB->setPlatform($this->platform);
		$this->datetimeColumnQB->setPlatform($this->platform);
		$this->usergroupColumnQB->setPlatform($this->platform);
	}

	/**
	 * @param int $id
	 *
	 * @return LegacyRow
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): LegacyRow {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t1.*')
			->from($this->table, 't1')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	private function buildFilterByColumnType($qb, array $filter, string $filterId): ?IQueryFunction {
		try {
			$columnQbClassName = 'OCA\Tables\Db\ColumnTypes\\';
			$type = explode("-", $filter['columnType'])[0];

			$columnQbClassName .= ucfirst($type).'ColumnQB';

			/** @var IColumnTypeQB $columnQb */
			$columnQb = Server::get($columnQbClassName);
			return $columnQb->addWhereFilterExpression($qb, $filter, $filterId);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->debug('Column type query builder class not found');
		}
		return null;
	}

	private function getInnerFilterExpressions($qb, $filterGroup, int $groupIndex): array {
		$innerFilterExpressions = [];
		foreach ($filterGroup as $index => $filter) {
			$innerFilterExpressions[] = $this->buildFilterByColumnType($qb, $filter, $groupIndex.$index);
		}
		return $innerFilterExpressions;
	}

	/**
	 * @param (float|int|string)[][][] $filters
	 *
	 * @psalm-param non-empty-list<list<array{columnId: int, operator: 'begins-with'|'contains'|'ends-with'|'is-empty'|'is-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal', value: float|int|string}>> $filters
	 */
	private function getFilterGroups(IQueryBuilder $qb, array $filters): array {
		$filterGroups = [];
		foreach ($filters as $groupIndex => $filterGroup) {
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
			case 'datetime-date-today': return date('Y-m-d') ? date('Y-m-d') : '';
			case 'datetime-date-start-of-year': return date('Y-01-01') ? date('Y-01-01') : '';
			case 'datetime-date-start-of-month': return date('Y-m-01') ? date('Y-m-01') : '';
			case 'datetime-date-start-of-week':
				$day = date('w');
				$result = date('m-d-Y', strtotime('-'.$day.' days'));
				return  $result ?: '';
			case 'datetime-time-now': return date('H:i');
			case 'datetime-now': return date('Y-m-d H:i') ? date('Y-m-d H:i') : '';
			default: return $unresolvedSearchValue;
		}
	}

	/**
	 * @param (int|string)[][] $sortArray
	 *
	 * @psalm-param list<array{columnId?: int, columnType?: string, mode?: 'ASC'|'DESC'}> $sortArray
	 */
	private function addOrderByRules(IQueryBuilder $qb, array $sortArray) {
		foreach ($sortArray as $index => $sortRule) {
			$sortMode = $sortRule['mode'];
			if (!in_array($sortMode, ['ASC', 'DESC'])) {
				continue;
			}
			$sortColumnPlaceholder = 'sortColumn'.$index;
			if ($sortRule['columnId'] < 0) {
				try {
					$orderString = SuperColumnQB::getMetaColumnName($sortRule['columnId']);
				} catch (InternalError $e) {
					return;
				}
			} else {
				if ($this->platform === IColumnTypeQB::DB_PLATFORM_PGSQL) {
					$orderString = 'c'.$sortRule['columnId'].'->>\'value\'';
				} elseif ($this->platform === IColumnTypeQB::DB_PLATFORM_SQLITE) {
					// here is an error for (multiple) sorting, works only for the first column at the moment
					$orderString = 'json_extract(t2.value, "$.value")';
				} else { // mariadb / mysql
					$orderString = 'JSON_EXTRACT(data, CONCAT( JSON_UNQUOTE(JSON_SEARCH(JSON_EXTRACT(data, \'$[*].columnId\'), \'one\', :'.$sortColumnPlaceholder.')), \'.value\'))';
				}
				if (str_starts_with($sortRule['columnType'], 'number')) {
					$orderString = 'CAST('.$orderString.' as decimal)';
				}
			}

			$qb->addOrderBy($qb->createFunction($orderString), $sortMode);
			$qb->setParameter($sortColumnPlaceholder, $sortRule['columnId'], IQueryBuilder::PARAM_INT);
		}
	}

	/**
	 * @param View $view
	 * @param $userId
	 * @return int
	 * @throws InternalError
	 */
	public function countRowsForView(View $view, $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'counter'))
			->from($this->table, 't1')
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($view->getTableId(), IQueryBuilder::PARAM_INT)));

		$neededColumnIds = $this->getAllColumnIdsFromView($view, $qb);
		try {
			$neededColumns = $this->columnMapper->getColumnTypes($neededColumnIds);
		} catch (Exception $e) {
			throw new InternalError('Could not get column types to count rows');
		}

		// Filter

		$this->addFilterToQuery($qb, $view, $neededColumns, $userId);

		try {
			$result = $this->findOneQuery($qb);
			return (int)$result['counter'];
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->warning('Exception occurred: '.$e->getMessage().' Returning 0.');
			return 0;
		}
	}


	public function getRowIdsOfView(View $view, $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t1.id')
			->from($this->table, 't1')
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($view->getTableId(), IQueryBuilder::PARAM_INT)));

		$neededColumnIds = $this->getAllColumnIdsFromView($view, $qb);
		$neededColumns = $this->columnMapper->getColumnTypes($neededColumnIds);

		// Filter

		$this->addFilterToQuery($qb, $view, $neededColumns, $userId);
		$result = $qb->executeQuery();
		try {
			$ids = [];
			while ($row = $result->fetch()) {
				$ids[] = $row['id'];
			}
			return $ids;
		} finally {
			$result->closeCursor();
		}
	}


	private function addFilterToQuery(IQueryBuilder $qb, View $view, array $neededColumnTypes, string $userId): void {
		$enrichedFilters = $view->getFilterArray();
		if (count($enrichedFilters) > 0) {
			foreach ($enrichedFilters as &$filterGroup) {
				foreach ($filterGroup as &$filter) {
					$filter['columnType'] = $neededColumnTypes[$filter['columnId']];
					// TODO move resolution for magic fields to service layer
					if(str_starts_with((string) $filter['value'], '@')) {
						$filter['value'] = $this->resolveSearchValue((string) $filter['value'], $userId);
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
	 * @param int $tableId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return LegacyRow[]
	 * @throws Exception
	 */
	public function findAllByTable(int $tableId, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t1.*')
			->from($this->table, 't1')
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
	 * @param string $userId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array
	 * @throws Exception
	 */
	public function findAllByView(View $view, string $userId, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t1.*')
			->from($this->table, 't1')
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($view->getTableId(), IQueryBuilder::PARAM_INT)));


		$neededColumnIds = $this->getAllColumnIdsFromView($view, $qb);
		$neededColumnsTypes = $this->columnMapper->getColumnTypes($neededColumnIds);

		// Filter

		$this->addFilterToQuery($qb, $view, $neededColumnsTypes, $userId);

		// Sorting

		$enrichedSort = $view->getSortArray();
		foreach ($enrichedSort as &$sort) {
			$sort['columnType'] = $neededColumnsTypes[$sort['columnId']];
		}
		$this->addOrderByRules($qb, $enrichedSort);

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}
		$rows = $this->findEntities($qb);
		foreach ($rows as &$row) {
			$row->setDataArray(array_filter($row->getDataArray(), function ($item) use ($view) {
				return in_array($item['columnId'], $view->getColumnsArray());
			}));
		}
		return $rows;
	}

	private function getAllColumnIdsFromView(View $view, IQueryBuilder $qb): array {
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
		$neededColumnIds = array_unique($neededColumnIds);
		if ($this->platform === IColumnTypeQB::DB_PLATFORM_PGSQL) {
			foreach ($neededColumnIds as $columnId) {
				if ($columnId >= 0) {
					/** @psalm-suppress ImplicitToStringCast */
					$qb->leftJoin("t1", $qb->createFunction('json_array_elements(t1.data)'), 'c' . intval($columnId), $qb->createFunction("CAST(c".intval($columnId).".value->>'columnId' AS int) = ".$columnId));
					// TODO Security
				}
			}
		}
		return $neededColumnIds;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function findNext(int $offsetId = -1): LegacyRow {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t1.*')
			->from($this->table, 't1')
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
			->from($this->table, 't1');

		$this->genericColumnQB->addWhereForFindAllWithColumn($qb, $columnId);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $tableId
	 * @return int
	 */
	public function countRows(int $tableId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'counter'));
		$qb->from($this->table, 't1');
		$qb->where(
			$qb->expr()->eq('table_id', $qb->createNamedParameter($tableId))
		);

		try {
			$result = $this->findOneQuery($qb);
			return (int)$result['counter'];
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->warning('Exception occurred: '.$e->getMessage().' Returning 0.');
			return 0;
		}
	}

	/**
	 * @param int $id
	 * @param View $view
	 * @return LegacyRow
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByView(int $id, View $view): LegacyRow {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t1.*')
			->from($this->table, 't1')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$row = $this->findEntity($qb);

		$row->setDataArray(array_filter($row->getDataArray(), function ($item) use ($view) {
			return in_array($item['columnId'], $view->getColumnsArray());
		}));

		return $row;
	}

	/**
	 * @param Column[] $columns
	 * @param LegacyRow $legacyRow
	 *
	 * @throws Exception
	 * @throws InternalError
	 */
	public function transferLegacyRow(LegacyRow $legacyRow, array $columns) {
		$this->rowMapper->insert($this->migrateLegacyRow($legacyRow, $columns), $columns);
	}

	/**
	 * @param LegacyRow $legacyRow
	 * @param Column[] $columns
	 * @return Row2
	 */
	public function migrateLegacyRow(LegacyRow $legacyRow, array $columns): Row2 {
		$row = new Row2();
		$row->setId($legacyRow->getId());
		$row->setTableId($legacyRow->getTableId());
		$row->setCreatedBy($legacyRow->getCreatedBy());
		$row->setCreatedAt($legacyRow->getCreatedAt());
		$row->setLastEditBy($legacyRow->getLastEditBy());
		$row->setLastEditAt($legacyRow->getLastEditAt());

		$legacyData = $legacyRow->getDataArray();
		$data = [];
		foreach ($legacyData as $legacyDatum) {
			$columnId = $legacyDatum['columnId'];
			if ($this->getColumnFromColumnsArray($columnId, $columns)) {
				$data[] = $legacyDatum;
			} else {
				$this->logger->warning("The row with id " . $row->getId() . " has a value for the column with id " . $columnId . ". But this column does not exist or is not part of the table " . $row->getTableId() . ". Will ignore this value abd continue.");
			}
		}
		$row->setData($data);

		return $row;
	}

	/**
	 * @param int $columnId
	 * @param Column[] $columns
	 * @return Column|null
	 */
	private function getColumnFromColumnsArray(int $columnId, array $columns): ?Column {
		foreach ($columns as $column) {
			if($column->getId() === $columnId) {
				return $column;
			}
		}
		return null;
	}
}
