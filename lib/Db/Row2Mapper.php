<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use DateTime;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\ColumnsHelper;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\TTransactional;
use OCP\DB\Exception;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Row2Mapper {
	use TTransactional;

	private RowSleeveMapper $rowSleeveMapper;
	private ?string $userId = null;
	private IDBConnection $db;
	private LoggerInterface $logger;
	protected UserHelper $userHelper;
	protected ColumnMapper $columnMapper;

	/* @var Column[] $columns */
	private array $columns = [];
	/* @var Column[] $columns */
	private array $allColumns = [];

	private ColumnsHelper $columnsHelper;

	public function __construct(?string $userId, IDBConnection $db, LoggerInterface $logger, UserHelper $userHelper, RowSleeveMapper $rowSleeveMapper, ColumnsHelper $columnsHelper, ColumnMapper $columnMapper) {
		$this->rowSleeveMapper = $rowSleeveMapper;
		$this->userId = $userId;
		$this->db = $db;
		$this->logger = $logger;
		$this->userHelper = $userHelper;
		$this->columnsHelper = $columnsHelper;
		$this->columnMapper = $columnMapper;
	}

	/**
	 * @param Row2 $row
	 * @return Row2
	 * @throws Exception
	 */
	public function delete(Row2 $row): Row2 {
		$this->db->beginTransaction();
		try {
			foreach ($this->columnsHelper->columns as $columnType) {
				$this->getCellMapperFromType($columnType)->deleteAllForRow($row->getId());
			}
			$this->rowSleeveMapper->deleteById($row->getId());
			$this->db->commit();
		} catch (Throwable $e) {
			$this->db->rollBack();
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new Exception(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		return $row;
	}

	/**
	 * @param int $id
	 * @param Column[] $columns
	 * @return Row2
	 * @throws InternalError
	 * @throws NotFoundError
	 */
	public function find(int $id, array $columns): Row2 {
		$this->setColumns($columns);
		$columnIdsArray = array_map(fn (Column $column) => $column->getId(), $columns);
		$rows = $this->getRows([$id], $columnIdsArray);
		if (count($rows) === 1) {
			return $rows[0];
		} elseif (count($rows) === 0) {
			$e = new Exception('Wanted row not found.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} else {
			$e = new Exception('Too many results for one wanted row.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
	}

	/**
	 * @throws InternalError
	 */
	public function findNextId(int $offsetId = -1): ?int {
		try {
			$rowSleeve = $this->rowSleeveMapper->findNext($offsetId);
		} catch (MultipleObjectsReturnedException | Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (DoesNotExistException $e) {
			return null;
		}
		return $rowSleeve->getId();
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getTableIdForRow(int $rowId): ?int {
		$rowSleeve = $this->rowSleeveMapper->find($rowId);
		return $rowSleeve->getTableId();
	}

	/**
	 * @param string $userId
	 * @param int $tableId
	 * @param array|null $filter
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return int[]
	 * @throws InternalError
	 */
	private function getWantedRowIds(string $userId, int $tableId, ?array $filter = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from('tables_row_sleeves', 'sleeves')
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)));
		if ($filter) {
			$this->addFilterToQuery($qb, $filter, $userId);
		}
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset !== null) {
			$qb->setFirstResult($offset);
		}

		try {
			$result = $this->db->executeQuery($qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes());
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), );
		}

		return array_map(fn (array $item) => $item['id'], $result->fetchAll());
	}

	/**
	 * @param Column[] $columns
	 * @param int $tableId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @param array|null $filter
	 * @param array|null $sort
	 * @param string|null $userId
	 * @return Row2[]
	 * @throws InternalError
	 */
	public function findAll(array $tableColumns, array $columns, int $tableId, ?int $limit = null, ?int $offset = null, ?array $filter = null, ?array $sort = null, ?string $userId = null): array {
		$this->setColumns($columns, $tableColumns);
		$columnIdsArray = array_map(fn (Column $column) => $column->getId(), $columns);

		$wantedRowIdsArray = $this->getWantedRowIds($userId, $tableId, $filter, $limit, $offset);

		// TODO add sorting

		return $this->getRows($wantedRowIdsArray, $columnIdsArray);
	}

	/**
	 * @param array $rowIds
	 * @param array $columnIds
	 * @return Row2[]
	 * @throws InternalError
	 */
	private function getRows(array $rowIds, array $columnIds): array {
		$qb = $this->db->getQueryBuilder();

		$qbSqlForColumnTypes = null;
		foreach ($this->columnsHelper->columns as $columnType) {
			$qbTmp = $this->db->getQueryBuilder();
			$qbTmp->select('row_id', 'column_id', 'last_edit_at', 'last_edit_by')
				->selectAlias($qb->expr()->castColumn('value', IQueryBuilder::PARAM_STR), 'value');

			// This is not ideal but I cannot think of a good way to abstract this away into the mapper right now
			// Ideally we dynamically construct this query depending on what additional selects the column type requires
			// however the union requires us to match the exact number of selects for each column type
			if ($columnType === Column::TYPE_USERGROUP) {
				$qbTmp->selectAlias($qb->expr()->castColumn('value_type', IQueryBuilder::PARAM_STR), 'value_type');
			} else {
				$qbTmp->selectAlias($qbTmp->createFunction('NULL'), 'value_type');
			}

			$qbTmp
				->from('tables_row_cells_' . $columnType)
				->where($qb->expr()->in('column_id', $qb->createNamedParameter($columnIds, IQueryBuilder::PARAM_INT_ARRAY, ':columnIds')))
				->andWhere($qb->expr()->in('row_id', $qb->createNamedParameter($rowIds, IQueryBuilder::PARAM_INT_ARRAY, ':rowsIds')));

			if ($qbSqlForColumnTypes) {
				$qbSqlForColumnTypes .= ' UNION ALL ' . $qbTmp->getSQL() . ' ';
			} else {
				$qbSqlForColumnTypes = '(' . $qbTmp->getSQL();
			}
		}
		$qbSqlForColumnTypes .= ')';

		$qb->select('row_id', 'column_id', 'created_by', 'created_at', 't1.last_edit_by', 't1.last_edit_at', 'value', 'table_id')
			// Also should be more generic (see above)
			->addSelect('value_type')
			->from($qb->createFunction($qbSqlForColumnTypes), 't1')
			->innerJoin('t1', 'tables_row_sleeves', 'rs', 'rs.id = t1.row_id');

		try {
			$result = $this->db->executeQuery($qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes());
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), );
		}

		try {
			$sleeves = $this->rowSleeveMapper->findMultiple($rowIds);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		try {
			$columnTypes = $this->columnMapper->getColumnTypes($columnIds);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		return $this->parseEntities($result, $sleeves, $columnTypes);
	}

	/**
	 * @throws InternalError
	 */
	private function addFilterToQuery(IQueryBuilder &$qb, array $filters, string $userId): void {
		// TODO move this into service
		$this->replacePlaceholderValues($filters, $userId);

		if (count($filters) > 0) {
			$qb->andWhere(
				$qb->expr()->orX(
					...$this->getFilterGroups($qb, $filters)
				)
			);
		}
	}

	private function replacePlaceholderValues(array &$filters, string $userId): void {
		foreach ($filters as &$filterGroup) {
			foreach ($filterGroup as &$filter) {
				if (substr($filter['value'], 0, 1) === '@') {
					$filter['value'] = $this->resolveSearchValue($filter['value'], $userId);
				}
			}
		}
	}

	/**
	 * @throws InternalError
	 */
	private function getFilterGroups(IQueryBuilder &$qb, array $filters): array {
		$filterGroups = [];
		foreach ($filters as $filterGroup) {
			$filterGroups[] = $qb->expr()->andX(...$this->getFilter($qb, $filterGroup));
		}
		return $filterGroups;
	}

	/**
	 * @throws InternalError
	 */
	private function getFilter(IQueryBuilder &$qb, array $filterGroup): array {
		$filterExpressions = [];
		foreach ($filterGroup as $filter) {
			$columnId = $filter['columnId'];
			// Fail if the filter is for a column that is not in the list and no meta column
			if (!isset($this->columns[$columnId]) && !isset($this->allColumns[$columnId]) && $columnId > 0) {
				throw new InternalError('No column found to build filter with for id ' . $columnId);
			}

			// if is normal column
			if ($columnId >= 0) {
				$column = $this->columns[$columnId] ?? $this->allColumns[$columnId];

				$sql = $qb->expr()->in(
					'id',
					$qb->createFunction($this->getFilterExpression($qb, $column, $filter['operator'], $filter['value'])->getSQL())
				);

				// if is meta data column
			} elseif ($columnId < 0) {
				$sql = $qb->expr()->in(
					'id',
					$qb->createFunction($this->getMetaFilterExpression($qb, $columnId, $filter['operator'], $filter['value'])->getSQL())
				);

				// if column id is unknown
			} else {
				$e = new Exception("Needed column (" . $columnId . ") not found.");
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			$filterExpressions[] = $sql;
		}
		return $filterExpressions;
	}

	/**
	 * @throws InternalError
	 */
	private function getFilterExpression(IQueryBuilder $qb, Column $column, string $operator, string $value): IQueryBuilder {
		if (!$this->columnsHelper->isSupportedColumnType((string)$column->getType())) {
			throw new InternalError('Column type is not supported');
		}
		$paramType = $this->getColumnDbParamType($column);
		$value = $this->getCellMapper($column)->filterValueToQueryParam($column, $value);

		// We try to match the requested value against the default before building the query
		// so we know if we shall include rows that have no entry in the column_TYPE tables upfront
		$includeDefault = false;
		$defaultValue = $this->getFormattedDefaultValue($column);

		$qb2 = $this->db->getQueryBuilder();
		$qb2->selectAlias('sl.id', 'row_id')
			->from('tables_row_sleeves', 'sl')
			->leftJoin('sl', 'tables_row_cells_' . $column->getType(), 'v', $qb->expr()->andX(
				$qb->expr()->eq('sl.id', 'v.row_id'),
				$qb->expr()->eq('v.column_id', $qb->createNamedParameter($column->getId(), IQueryBuilder::PARAM_INT)),
			));
		$qb2->where(
			$qb->expr()->eq('sl.table_id', $qb->createNamedParameter($column->getTableId(), IQueryBuilder::PARAM_INT))
		);

		switch ($operator) {
			case 'begins-with':
				$includeDefault = str_starts_with($defaultValue, $value);
				$filterExpression = $qb->expr()->like('value', $qb->createNamedParameter($this->db->escapeLikeParameter($value) . '%', $paramType));
				break;
			case 'ends-with':
				$includeDefault = str_ends_with($defaultValue, $value);
				$filterExpression = $qb->expr()->like('value', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($value), $paramType));
				break;
			case 'contains':
				$includeDefault = str_contains($defaultValue, $value);
				if ($column->getType() === 'selection' && $column->getSubtype() === 'multi') {
					$value = str_replace(['"', '\''], '', $value);
					$filterExpression = $qb2->expr()->orX(
						$qb->expr()->like('value', $qb->createNamedParameter('[' . $this->db->escapeLikeParameter($value) . ']')),
						$qb->expr()->like('value', $qb->createNamedParameter('[' . $this->db->escapeLikeParameter($value) . ',%')),
						$qb->expr()->like('value', $qb->createNamedParameter('%,' . $this->db->escapeLikeParameter($value) . ']%')),
						$qb->expr()->like('value', $qb->createNamedParameter('%,' . $this->db->escapeLikeParameter($value) . ',%'))
					);
					break;
				}
				$filterExpression = $qb->expr()->like('value', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($value) . '%', $paramType));
				break;
			case 'is-equal':
				$includeDefault = $defaultValue === $value;
				if ($column->getType() === 'selection' && $column->getSubtype() === 'multi') {
					$value = str_replace(['"', '\''], '', $value);
					$filterExpression = $qb->expr()->eq('value', $qb->createNamedParameter('[' . $this->db->escapeLikeParameter($value) . ']', $paramType));
					break;
				}
				$filterExpression = $qb->expr()->eq('value', $qb->createNamedParameter($value, $paramType));
				break;
			case 'is-greater-than':
				$includeDefault = $column->getNumberDefault() > (float)$value;
				$filterExpression = $qb->expr()->gt('value', $qb->createNamedParameter($value, $paramType));
				break;
			case 'is-greater-than-or-equal':
				$includeDefault = $column->getNumberDefault() >= (float)$value;
				$filterExpression = $qb->expr()->gte('value', $qb->createNamedParameter($value, $paramType));
				break;
			case 'is-lower-than':
				$includeDefault = $column->getNumberDefault() < (float)$value;
				$filterExpression = $qb->expr()->lt('value', $qb->createNamedParameter($value, $paramType));
				break;
			case 'is-lower-than-or-equal':
				$includeDefault = $column->getNumberDefault() <= (float)$value;
				$filterExpression = $qb->expr()->lte('value', $qb->createNamedParameter($value, $paramType));
				break;
			case 'is-empty':
				$includeDefault = empty($defaultValue);
				$filterExpression = $qb->expr()->isNull('value');
				break;
			default:
				throw new InternalError('Operator ' . $operator . ' is not supported.');
		}

		return $qb2->andWhere(
			$qb->expr()->orX(
				...array_values(array_filter([
					$filterExpression,
					$includeDefault ? $qb->expr()->isNull('value') : null
				])),
			),
		);
	}

	/**
	 * @throws InternalError
	 */
	private function getMetaFilterExpression(IQueryBuilder $qb, int $columnId, string $operator, string $value): IQueryBuilder {
		$qb2 = $this->db->getQueryBuilder();
		$qb2->select('id');
		$qb2->from('tables_row_sleeves');

		switch ($columnId) {
			case Column::TYPE_META_ID:
				$qb2->where($this->getSqlOperator($operator, $qb, 'id', (int)$value, IQueryBuilder::PARAM_INT));
				break;
			case Column::TYPE_META_CREATED_BY:
				$qb2->where($this->getSqlOperator($operator, $qb, 'created_by', $value, IQueryBuilder::PARAM_STR));
				break;
			case Column::TYPE_META_CREATED_AT:
				$value = new \DateTimeImmutable($value);
				$qb2->where($this->getSqlOperator($operator, $qb, 'created_at', $value, IQueryBuilder::PARAM_DATE));
				break;
			case Column::TYPE_META_UPDATED_BY:
				$qb2->where($this->getSqlOperator($operator, $qb, 'last_edit_by', $value, IQueryBuilder::PARAM_STR));
				break;
			case Column::TYPE_META_UPDATED_AT:
				$value = new \DateTimeImmutable($value);
				$qb2->where($this->getSqlOperator($operator, $qb, 'last_edit_at', $value, IQueryBuilder::PARAM_DATE));
				break;
		}
		return $qb2;
	}

	/**
	 * @param string $operator
	 * @param IQueryBuilder $qb
	 * @param string $columnName
	 * @param mixed $value
	 * @param mixed $paramType
	 * @return string
	 * @throws InternalError
	 */
	private function getSqlOperator(string $operator, IQueryBuilder $qb, string $columnName, $value, $paramType): string {
		switch ($operator) {
			case 'begins-with':
				return $qb->expr()->like($columnName, $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($value), $paramType));
			case 'ends-with':
				return $qb->expr()->like($columnName, $qb->createNamedParameter($this->db->escapeLikeParameter($value) . '%', $paramType));
			case 'contains':
				return $qb->expr()->like($columnName, $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($value) . '%', $paramType));
			case 'is-equal':
				return $qb->expr()->eq($columnName, $qb->createNamedParameter($value, $paramType));
			case 'is-greater-than':
				return $qb->expr()->gt($columnName, $qb->createNamedParameter($value, $paramType));
			case 'is-greater-than-or-equal':
				return $qb->expr()->gte($columnName, $qb->createNamedParameter($value, $paramType));
			case 'is-lower-than':
				return $qb->expr()->lt($columnName, $qb->createNamedParameter($value, $paramType));
			case 'is-lower-than-or-equal':
				return $qb->expr()->lte($columnName, $qb->createNamedParameter($value, $paramType));
			case 'is-empty':
				return $qb->expr()->isNull($columnName);
			default:
				throw new InternalError('Operator ' . $operator . ' is not supported.');
		}
	}

	/** @noinspection DuplicatedCode */
	private function resolveSearchValue(string $placeholder, string $userId): string {
		if (substr($placeholder, 0, 14) === '@selection-id-') {
			return substr($placeholder, 14);
		}
		switch (ltrim($placeholder, '@')) {
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
				$result = date('Y-m-d', strtotime('-'.$day.' days'));
				return  $result ?: '';
			case 'datetime-time-now': return date('H:i');
			case 'datetime-now': return date('Y-m-d H:i') ? date('Y-m-d H:i') : '';
			default: return $placeholder;
		}
	}

	/**
	 * @param IResult $result
	 * @param RowSleeve[] $sleeves
	 * @return Row2[]
	 * @throws InternalError
	 */
	private function parseEntities(IResult $result, array $sleeves, array $columnTypes): array {
		$data = $result->fetchAll();

		$rows = [];
		foreach ($sleeves as $sleeve) {
			$rows[$sleeve->getId()] = new Row2();
			$rows[$sleeve->getId()]->setId($sleeve->getId());
			$rows[$sleeve->getId()]->setCreatedBy($sleeve->getCreatedBy());
			$rows[$sleeve->getId()]->setCreatedAt($sleeve->getCreatedAt());
			$rows[$sleeve->getId()]->setLastEditBy($sleeve->getLastEditBy());
			$rows[$sleeve->getId()]->setLastEditAt($sleeve->getLastEditAt());
			$rows[$sleeve->getId()]->setTableId($sleeve->getTableId());
		}

		$rowValues = [];
		$keyToColumnId = [];
		$keyToRowId = [];

		foreach ($data as $rowData) {
			if (!isset($rowData['row_id']) || !isset($rows[$rowData['row_id']])) {
				break;
			}

			$columnType = $this->columns[$rowData['column_id']]->getType();
			$cellClassName = 'OCA\Tables\Db\RowCell' . ucfirst($columnType);
			$entity = call_user_func($cellClassName .'::fromRowData', $rowData); // >5.2.3
			$cellMapper = $this->getCellMapperFromType($columnType);
			$value = $cellMapper->formatEntity($this->columns[$rowData['column_id']], $entity);
			$compositeKey = (string)$rowData['row_id'] . ',' . (string)$rowData['column_id'];

			if ($cellMapper->hasMultipleValues()) {
				if (array_key_exists($compositeKey, $rowValues)) {
					$rowValues[$compositeKey][] = $value;
				} else {
					$rowValues[$compositeKey] = [$value];
				}
			} else {
				$rowValues[$compositeKey] = $value;
			}
			$keyToColumnId[$compositeKey] = $rowData['column_id'];
			$keyToRowId[$compositeKey] = $rowData['row_id'];
		}

		foreach ($rowValues as $compositeKey => $value) {
			$rows[$keyToRowId[$compositeKey]]->addCell($keyToColumnId[$compositeKey], $value);
		}

		// format an array without keys
		$return = [];
		foreach ($rows as $row) {
			$return[] = $row;
		}
		return $return;
	}

	/**
	 * @throws InternalError
	 */
	public function isRowInViewPresent(int $rowId, View $view, string $userId): bool {
		return in_array($rowId, $this->getWantedRowIds($userId, $view->getTableId(), $view->getFilterArray()));
	}

	/**
	 * @param Row2 $row
	 * @param Column[] $columns
	 * @return Row2
	 * @throws InternalError
	 * @throws Exception
	 */
	public function insert(Row2 $row, array $columns): Row2 {
		$this->setColumns($columns);

		if ($row->getId()) {
			// if row has an id from migration or import etc.
			$rowSleeve = $this->createRowSleeveFromExistingData($row->getId(), $row->getTableId(), $row->getCreatedAt(), $row->getCreatedBy(), $row->getLastEditBy(), $row->getLastEditAt());
		} else {
			// create a new row sleeve to get a new rowId
			$rowSleeve = $this->createNewRowSleeve($row->getTableId());
			$row->setId($rowSleeve->getId());
		}

		// if the table/view has columns
		if (count($columns) > 0) {
			// write all cells to its db-table
			foreach ($row->getData() as $cell) {
				$this->insertCell($rowSleeve->getId(), $cell['columnId'], $cell['value'], $rowSleeve->getLastEditAt(), $rowSleeve->getLastEditBy());
			}
		}

		return $row;
	}

	/**
	 * @throws InternalError
	 */
	public function update(Row2 $row, array $columns): Row2 {
		if (!$columns) {
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': columns are missing');
		}
		$this->setColumns($columns);

		// if nothing has changed
		if (count($row->getChangedCells()) === 0) {
			return $row;
		}

		// update meta data for sleeve
		try {
			$sleeve = $this->rowSleeveMapper->find($row->getId());
			$this->updateMetaData($sleeve);
			$this->rowSleeveMapper->update($sleeve);
		} catch (DoesNotExistException | MultipleObjectsReturnedException | Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// write all changed cells to its db-table
		foreach ($row->getChangedCells() as $cell) {
			$this->insertOrUpdateCell($sleeve->getId(), $cell['columnId'], $cell['value']);
		}

		return $row;
	}

	/**
	 * @throws Exception
	 */
	private function createNewRowSleeve(int $tableId): RowSleeve {
		$rowSleeve = new RowSleeve();
		$rowSleeve->setTableId($tableId);
		$this->updateMetaData($rowSleeve, true);
		return $this->rowSleeveMapper->insert($rowSleeve);
	}

	/**
	 * @throws Exception
	 */
	private function createRowSleeveFromExistingData(int $id, int $tableId, string $createdAt, string $createdBy, string $lastEditBy, string $lastEditAt): RowSleeve {
		$rowSleeve = new RowSleeve();
		$rowSleeve->setId($id);
		$rowSleeve->setTableId($tableId);
		$rowSleeve->setCreatedBy($createdBy);
		$rowSleeve->setCreatedAt($createdAt);
		$rowSleeve->setLastEditBy($lastEditBy);
		$rowSleeve->setLastEditAt($lastEditAt);
		return $this->rowSleeveMapper->insert($rowSleeve);
	}

	/**
	 * Updates the last_edit_by and last_edit_at data
	 * optional adds the created_by and created_at data
	 *
	 * @param RowSleeve|RowCellSuper $entity
	 * @param bool $setCreate
	 * @param string|null $lastEditAt
	 * @param string|null $lastEditBy
	 * @return void
	 */
	private function updateMetaData($entity, bool $setCreate = false, ?string $lastEditAt = null, ?string $lastEditBy = null): void {
		$time = new DateTime();
		if ($setCreate) {
			$entity->setCreatedBy($this->userId);
			$entity->setCreatedAt($time->format('Y-m-d H:i:s'));
		}
		$entity->setLastEditBy($lastEditBy ?: $this->userId);
		$entity->setLastEditAt($lastEditAt ?: $time->format('Y-m-d H:i:s'));
	}

	/**
	 * Insert a cell to its specific db-table
	 *
	 * @throws InternalError
	 */
	private function insertCell(int $rowId, int $columnId, $value, ?string $lastEditAt = null, ?string $lastEditBy = null): void {
		if (!isset($this->columns[$columnId])) {
			$e = new Exception("Can not insert cell, because the given column-id is not known");
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}


		// insert new cell
		$column = $this->columns[$columnId];
		$cellMapper = $this->getCellMapper($column);

		$column = $this->columns[$columnId];

		try {
			$cellClassName = 'OCA\Tables\Db\RowCell' . ucfirst($this->columns[$columnId]->getType());
			if ($cellMapper->hasMultipleValues()) {
				foreach ($value as $val) {
					/** @var RowCellSuper $cell */
					$cell = new $cellClassName();
					$cell->setRowIdWrapper($rowId);
					$cell->setColumnIdWrapper($columnId);
					$this->updateMetaData($cell, false, $lastEditAt, $lastEditBy);
					$cellMapper->applyDataToEntity($column, $cell, $val);
					$cellMapper->insert($cell);
				}
			} else {
				/** @var RowCellSuper $cell */
				$cell = new $cellClassName();
				$cell->setRowIdWrapper($rowId);
				$cell->setColumnIdWrapper($columnId);
				$this->updateMetaData($cell, false, $lastEditAt, $lastEditBy);
				$cellMapper->applyDataToEntity($column, $cell, $value);
				$cellMapper->insert($cell);
			}
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError('Failed to insert column: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param RowCellSuper $cell
	 * @param RowCellMapperSuper $mapper
	 * @param mixed $value the value should be parsed to the correct format within the row service
	 * @param Column $column
	 * @throws InternalError
	 */
	private function updateCell(RowCellSuper $cell, RowCellMapperSuper $mapper, $value, Column $column): void {
		$this->getCellMapper($column)->applyDataToEntity($column, $cell, $value);
		$this->updateMetaData($cell);
		$mapper->updateWrapper($cell);
	}

	/**
	 * @throws InternalError
	 */
	private function insertOrUpdateCell(int $rowId, int $columnId, $value): void {
		$cellMapper = $this->getCellMapper($this->columns[$columnId]);
		try {
			if ($cellMapper->hasMultipleValues()) {
				$this->atomic(function () use ($cellMapper, $rowId, $columnId, $value) {
					// For a usergroup field with mutiple values, each is inserted as a new cell
					// we need to delete all previous cells for this row and column, otherwise we get duplicates
					$cellMapper->deleteAllForColumnAndRow($columnId, $rowId);
					$this->insertCell($rowId, $columnId, $value);
				}, $this->db);
			} else {
				$cell = $cellMapper->findByRowAndColumn($rowId, $columnId);
				$this->updateCell($cell, $cellMapper, $value, $this->columns[$columnId]);
			}
		} catch (DoesNotExistException) {
			$this->insertCell($rowId, $columnId, $value);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
	}

	/**
	 * @param Column[] $columns
	 */
	private function setColumns(array $columns, array $tableColumns = []): void {
		foreach ($columns as $column) {
			$this->columns[$column->getId()] = $column;
		}

		// We hold a list of all table columns to be used in filter expression building for those not visible in the view
		foreach ($tableColumns as $column) {
			$this->allColumns[$column->getId()] = $column;
		}

	}

	private function getCellMapper(Column $column): RowCellMapperSuper {
		return $this->getCellMapperFromType($column->getType());
	}

	private function getCellMapperFromType(string $columnType): RowCellMapperSuper {
		$cellMapperClassName = 'OCA\Tables\Db\RowCell'.ucfirst($columnType).'Mapper';
		/** @var RowCellMapperSuper $cellMapper */
		try {
			return Server::get($cellMapperClassName);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
	}

	/**
	 * @throws InternalError
	 */
	private function getColumnDbParamType(Column $column) {
		return $this->getCellMapper($column)->getDbParamType();
	}

	/**
	 * @throws InternalError
	 */
	public function deleteDataForColumn(Column $column): void {
		try {
			$this->getCellMapper($column)->deleteAllForColumn($column->getId());
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
	}

	/**
	 * @param int $tableId
	 * @param Column[] $columns
	 * @return void
	 */
	public function deleteAllForTable(int $tableId, array $columns): void {
		foreach ($columns as $column) {
			try {
				$this->deleteDataForColumn($column);
			} catch (InternalError $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
			}
		}
		try {
			$this->rowSleeveMapper->deleteAllForTable($tableId);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
		}
	}

	public function countRowsForTable(int $tableId): int {
		return $this->rowSleeveMapper->countRows($tableId);
	}

	/**
	 * @param View $view
	 * @param string $userId
	 * @param Column[] $columns
	 * @return int
	 */
	public function countRowsForView(View $view, string $userId, array $columns): int {
		$this->setColumns($columns);

		$filter = $view->getFilterArray();
		try {
			$rowIds = $this->getWantedRowIds($userId, $view->getTableId(), $filter);
		} catch (InternalError $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			$rowIds = [];
		}
		return count($rowIds);
	}

	private function getFormattedDefaultValue(Column $column) {
		$defaultValue = null;
		switch ($column->getType()) {
			case Column::TYPE_SELECTION:
				$defaultValue = $this->getCellMapper($column)->filterValueToQueryParam($column, $column->getSelectionDefault());
				break;
			case Column::TYPE_DATETIME:
				$defaultValue = $this->getCellMapper($column)->filterValueToQueryParam($column, $column->getDatetimeDefault());
				break;
			case Column::TYPE_NUMBER:
				$defaultValue = $this->getCellMapper($column)->filterValueToQueryParam($column, $column->getNumberDefault());
				break;
			case Column::TYPE_TEXT:
				$defaultValue = $this->getCellMapper($column)->filterValueToQueryParam($column, $column->getTextDefault());
				break;
			case Column::TYPE_USERGROUP:
				$defaultValue = $this->getCellMapper($column)->filterValueToQueryParam($column, $column->getUsergroupDefault());
				break;
		}
		return $defaultValue;
	}
}
