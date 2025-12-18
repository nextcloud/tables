<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use DateTime;
use DateTimeImmutable;
use OCA\Tables\Constants\UsergroupType;
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
	private ?string $userId;
	private IDBConnection $db;
	private LoggerInterface $logger;
	protected UserHelper $userHelper;
	protected ColumnMapper $columnMapper;

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
		$columnIdsArray = array_map(fn (Column $column) => $column->getId(), $columns);
		$rows = $this->getRows([$id], $columnIdsArray);

		if (count($rows) === 1) {
			return $rows[0];
		}

		if (count($rows) === 0) {
			$e = new Exception('Wanted row not found.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$e = new Exception('Too many results for one wanted row.');
		$this->logger->error($e->getMessage(), ['exception' => $e]);
		throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
	}

	/**
	 * @throws InternalError
	 */
	public function findNextId(int $offsetId = -1): ?int {
		try {
			$rowSleeve = $this->rowSleeveMapper->findNext($offsetId);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		} catch (DoesNotExistException) {
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
	 * @return int[]
	 * @throws InternalError
	 */
	private function getWantedRowIds(string $userId, int $tableId, ?array $filter = null, ?array $sort = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('sleeves.id')
			->from('tables_row_sleeves', 'sleeves')
			->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)));

		if ($filter) {
			$this->addFilterToQuery($qb, $filter, $userId);
		}

		$this->addSortQueryForMultipleSleeveFinder($qb, 'sleeves', $sort);

		$qb->groupBy('sleeves.id');

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
	 * @param int[] $showColumnIds
	 * @param int $tableId
	 * @param int|null $limit
	 * @param int|null $offset
	 * @param array|null $filter
	 * @param array|null $sort
	 * @param string|null $userId
	 * @return Row2[]
	 * @throws InternalError
	 */
	public function findAll(array $showColumnIds, int $tableId, ?int $limit = null, ?int $offset = null, ?array $filter = null, ?array $sort = null, ?string $userId = null): array {
		try {
			$this->columnMapper->preloadColumns($showColumnIds, $filter, $sort);

			$wantedRowIdsArray = $this->getWantedRowIds($userId, $tableId, $filter, $sort, $limit, $offset);

			// Get rows without SQL sorting
			$rows = $this->getRows($wantedRowIdsArray, $showColumnIds);

			// Sort rows in PHP to preserve the order from getWantedRowIds
			return $this->sortRowsByIds($rows, $wantedRowIdsArray);
		} catch (DoesNotExistException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
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
			$result = $qb->executeQuery();
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

		return $this->parseEntities($result, $sleeves);
	}

	/**
	 * @throws InternalError
	 */
	private function addFilterToQuery(IQueryBuilder $qb, array $filters, string $userId): void {
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

	/**
	 * This method is passed to RowSleeveMapper::findMultiple() when the rows need sorting. The RowSleeveMapper does not have
	 * knowledge about the column information, as they reside in this class, and the mapper is called from here.
	 *
	 * @throws InternalError
	 */
	private function addSortQueryForMultipleSleeveFinder(IQueryBuilder $qb, string $sleevesAlias, ?array $sort): void {
		if ($sort === null) {
			return;
		}

		$i = 1;
		foreach ($sort as $sortData) {
			if (!in_array($sortData['mode'], ['ASC', 'DESC'])) {
				continue;
			}
			try {
				$column = $sortData['columnId'] > 0 ? $this->columnMapper->find($sortData['columnId']) : null;
			} catch (DoesNotExistException) {
				$this->logger->debug('No column found to build filter with for id ' . $sortData['columnId']);
				continue;
			}

			// if is normal column
			if ($sortData['columnId'] >= 0) {
				$valueTable = 'tables_row_cells_' . $column->getType();
				$alias = 'sort' . $i;
				$qb->leftJoin($sleevesAlias, $valueTable, $alias,
					$qb->expr()->andX(
						$qb->expr()->eq($sleevesAlias . '.id', $alias . '.row_id'),
						$qb->expr()->eq($alias . '.column_id', $qb->createNamedParameter($sortData['columnId']))
					)
				);
				$qb->addOrderBy($qb->func()->max($alias . '.value'), $sortData['mode']);
			} elseif (Column::isValidMetaTypeId($sortData['columnId'])) {
				$fieldName = match ($sortData['columnId']) {
					Column::TYPE_META_ID => 'id',
					Column::TYPE_META_CREATED_BY => 'created_by',
					Column::TYPE_META_CREATED_AT => 'created_at',
					Column::TYPE_META_UPDATED_BY => 'last_edit_by',
					Column::TYPE_META_UPDATED_AT => 'last_edit_at',
					default => null,
				};

				if ($fieldName === null) {
					// Can happen, when–
					// … a new meta column was introduced, but not considered here
					// … a meta column was removed and existing sort rules are not being adapted
					// those case are being ignored, but would require developer attention
					$this->logger->error('No meta column (ID: {columnId}) found for sorting id', [
						'columnId' => $sortData['columnId'],
					]);
					continue;
				}

				$qb->addOrderBy($qb->func()->max($sleevesAlias . '.' . $fieldName), $sortData['mode']);
			}
			$i++;
		}
	}

	private function replacePlaceholderValues(array &$filters, string $userId): void {
		foreach ($filters as &$filterGroup) {
			foreach ($filterGroup as &$filter) {
				if (str_starts_with($filter['value'], '@')) {
					$columnId = (int)($filter['columnId'] ?? 0);
					$column = $columnId > 0 ? $this->columnMapper->find($columnId) : null;
					$filter['value'] = $this->columnsHelper->resolveSearchValue($filter['value'], $userId, $column);
				}
			}
		}
	}

	/**
	 * @throws InternalError
	 */
	private function getFilterGroups(IQueryBuilder $qb, array $filters): array {
		$filterGroups = [];
		foreach ($filters as $filterGroup) {
			$filterGroups[] = $qb->expr()->andX(...$this->getFilter($qb, $filterGroup));
		}
		return $filterGroups;
	}

	/**
	 * @throws InternalError
	 */
	private function getFilter(IQueryBuilder $qb, array $filterGroup): array {
		$filterExpressions = [];
		foreach ($filterGroup as $filter) {
			$columnId = $filter['columnId'];
			$column = $columnId > 0 ? $this->columnMapper->find($columnId) : null;
			// Fail if the filter is for a column that is not in the list and no meta column
			if ($column === null && $columnId > 0) {
				throw new InternalError('No column found to build filter with for id ' . $columnId);
			}

			// if is normal column
			if ($columnId >= 0) {
				$sql = $qb->expr()->in(
					'sleeves.id',
					$qb->createFunction($this->getFilterExpression($qb, $column, $filter['operator'], $filter['value'])->getSQL())
				);

				// if is meta data column
			} elseif ($columnId < 0) {
				$sql = $qb->expr()->in(
					'sleeves.id',
					$qb->createFunction($this->getMetaFilterExpression($qb, $columnId, $filter['operator'], $filter['value'])->getSQL())
				);

				// if column id is unknown
			} else {
				$e = new Exception('Needed column (' . $columnId . ') not found.');
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
	private function getFilterExpression(IQueryBuilder $qb, Column $column, string $operator, string|array $value): IQueryBuilder {
		$paramType = $this->getColumnDbParamType($column);
		try {
			$value = $this->getCellMapper($column)->filterValueToQueryParam($column, $value);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Cannot filter, because the column does not exist', ['exception' => $e]);
			throw new InternalError(get_class($this) . '::' . __FUNCTION__ . ': Cannot filter, because the column does not exist');
		}

		// We try to match the requested value against the default before building the query
		// so we know if we shall include rows that have no entry in the column_TYPE tables upfront
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
				$includeDefault = str_starts_with((string)($defaultValue ?? ''), $value);
				$filterExpression = $qb->expr()->like('value', $qb->createNamedParameter($this->db->escapeLikeParameter($value) . '%', $paramType));
				break;
			case 'ends-with':
				$includeDefault = str_ends_with((string)($defaultValue ?? ''), $value);
				$filterExpression = $qb->expr()->like('value', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($value), $paramType));
				break;
			case 'contains':
				$filterExpressions = [];
				if (is_array($value) && $column->getType() === Column::TYPE_USERGROUP) {
					$filterExpressions[] = $qb2->expr()->andX(
						$qb->expr()->eq('value', $qb->createNamedParameter($value[UsergroupType::USER])),
						$qb->expr()->eq('value_type', $qb->createNamedParameter(UsergroupType::USER, IQueryBuilder::PARAM_INT))
					);
					if (!empty($value[UsergroupType::GROUP])) {
						$filterExpressions[] = $qb2->expr()->andX(
							$qb->expr()->in('value', $qb->createNamedParameter($value[UsergroupType::GROUP], IQueryBuilder::PARAM_STR_ARRAY)),
							$qb->expr()->eq('value_type', $qb->createNamedParameter(UsergroupType::GROUP, IQueryBuilder::PARAM_INT))
						);
					}
					if (!empty($value[UsergroupType::CIRCLE])) {
						$filterExpressions[] = $qb2->expr()->andX(
							$qb->expr()->in('value', $qb->createNamedParameter($value[UsergroupType::CIRCLE], IQueryBuilder::PARAM_STR_ARRAY)),
							$qb->expr()->eq('value_type', $qb->createNamedParameter(UsergroupType::CIRCLE, IQueryBuilder::PARAM_INT))
						);
					}
					$filterExpression = $qb2->expr()->orX(...$filterExpressions);
					$includeDefault = false;

					break;
				}

				$includeDefault = str_contains((string)($defaultValue ?? ''), $value);
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
			case 'does-not-contain':
				$filterExpressions = [];
				if (is_array($value) && $column->getType() === Column::TYPE_USERGROUP) {
					$filterExpressions[] = $qb2->expr()->andX(
						$qb->expr()->neq('value', $qb->createNamedParameter($value[UsergroupType::USER])),
						$qb->expr()->eq('value_type', $qb->createNamedParameter(UsergroupType::USER, IQueryBuilder::PARAM_INT))
					);
					if (!empty($value[UsergroupType::GROUP])) {
						$filterExpressions[] = $qb2->expr()->andX(
							$qb->expr()->notIn('value', $qb->createNamedParameter($value[UsergroupType::GROUP], IQueryBuilder::PARAM_STR_ARRAY)),
							$qb->expr()->eq('value_type', $qb->createNamedParameter(UsergroupType::GROUP, IQueryBuilder::PARAM_INT))
						);
					}
					if (!empty($value[UsergroupType::CIRCLE])) {
						$filterExpressions[] = $qb2->expr()->andX(
							$qb->expr()->notIn('value', $qb->createNamedParameter($value[UsergroupType::CIRCLE], IQueryBuilder::PARAM_STR_ARRAY)),
							$qb->expr()->eq('value_type', $qb->createNamedParameter(UsergroupType::CIRCLE, IQueryBuilder::PARAM_INT))
						);
					}
					$filterExpression = $qb2->expr()->andX(...$filterExpressions);
					$includeDefault = false;

					break;
				}

				$includeDefault = !str_contains((string)($defaultValue ?? ''), $value);
				if ($column->getType() === 'selection' && $column->getSubtype() === 'multi') {
					$value = str_replace(['"', '\''], '', $value);
					$filterExpression = $qb2->expr()->andX(
						$qb->expr()->notLike('value', $qb->createNamedParameter('[' . $this->db->escapeLikeParameter($value) . ']')),
						$qb->expr()->notLike('value', $qb->createNamedParameter('[' . $this->db->escapeLikeParameter($value) . ',%')),
						$qb->expr()->notLike('value', $qb->createNamedParameter('%,' . $this->db->escapeLikeParameter($value) . ']%')),
						$qb->expr()->notLike('value', $qb->createNamedParameter('%,' . $this->db->escapeLikeParameter($value) . ',%'))
					);
					break;
				}
				$filterExpression = $qb->expr()->notLike('value', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($value) . '%', $paramType));
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
			case 'is-not-equal':
				$includeDefault = $defaultValue === $value;
				if ($column->getType() === 'selection' && $column->getSubtype() === 'multi') {
					$value = str_replace(['"', '\''], '', $value);
					$filterExpression = $qb->expr()->neq('value', $qb->createNamedParameter('[' . $this->db->escapeLikeParameter($value) . ']', $paramType));
					break;
				}
				$filterExpression = $qb->expr()->neq('value', $qb->createNamedParameter($value, $paramType));
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
				if ($column->getType() === Column::TYPE_TEXT) {
					$filterExpression = $qb2->expr()->orX(
						$qb->expr()->isNull('value'),
						$qb->expr()->eq('value', $qb->createNamedParameter('', $paramType))
					);
				} else {
					$filterExpression = $qb->expr()->isNull('value');
				}
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
				$qb2->where($this->getSqlOperator($operator, $qb, 'created_at', new DateTimeImmutable($value), IQueryBuilder::PARAM_DATE));
				break;
			case Column::TYPE_META_UPDATED_BY:
				$qb2->where($this->getSqlOperator($operator, $qb, 'last_edit_by', $value, IQueryBuilder::PARAM_STR));
				break;
			case Column::TYPE_META_UPDATED_AT:
				$qb2->where($this->getSqlOperator($operator, $qb, 'last_edit_at', new DateTimeImmutable($value), IQueryBuilder::PARAM_DATE));
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
			case 'is-not-equal':
				return $qb->expr()->neq($columnName, $qb->createNamedParameter($value, $paramType));
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

	/**
	 * @param IResult $result
	 * @param RowSleeve[] $sleeves
	 * @return Row2[]
	 * @throws InternalError
	 */
	private function parseEntities(IResult $result, array $sleeves): array {
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
		$cellMapperCache = [];

		while ($rowData = $result->fetch()) {
			if (!isset($rowData['row_id'], $rows[$rowData['row_id']])) {
				break;
			}

			$column = $this->columnMapper->find($rowData['column_id']);
			$columnType = $column->getType();
			$cellClassName = 'OCA\Tables\Db\RowCell' . ucfirst($columnType);
			$entity = call_user_func($cellClassName . '::fromRowData', $rowData); // >5.2.3
			if (!isset($cellMapperCache[$columnType])) {
				$cellMapperCache[$columnType] = $this->getCellMapperFromType($columnType);
			}
			$value = $cellMapperCache[$columnType]->formatEntity($column, $entity);
			$compositeKey = (string)$rowData['row_id'] . ',' . (string)$rowData['column_id'];
			if ($cellMapperCache[$columnType]->hasMultipleValues()) {
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

		return array_values($rows);
	}

	/**
	 * @throws InternalError
	 */
	public function isRowInViewPresent(int $rowId, View $view, string $userId): bool {
		return in_array($rowId, $this->getWantedRowIds($userId, $view->getTableId(), $view->getFilterArray()));
	}

	/**
	 * @param Row2 $row
	 * @return Row2
	 * @throws InternalError
	 * @throws Exception
	 */
	public function insert(Row2 $row): Row2 {
		if ($row->getId()) {
			// if row has an id from migration or import etc.
			$rowSleeve = $this->createRowSleeveFromExistingData($row->getId(), $row->getTableId(), $row->getCreatedAt(), $row->getCreatedBy(), $row->getLastEditBy(), $row->getLastEditAt());
		} else {
			// create a new row sleeve to get a new rowId
			$rowSleeve = $this->createNewRowSleeve($row->getTableId());
			$row->setId($rowSleeve->getId());
		}

		// write all cells to its db-table
		foreach ($row->getData() as $cell) {
			$this->insertCell($rowSleeve->getId(), $cell['columnId'], $cell['value'], $rowSleeve->getLastEditAt(), $rowSleeve->getLastEditBy());
		}

		return $row;
	}

	/**
	 * @throws InternalError
	 */
	public function update(Row2 $row): Row2 {
		$changedCells = $row->getChangedCells();
		// if nothing has changed
		if (count($changedCells) === 0) {
			return $row;
		}

		// update meta data for sleeve
		try {
			$sleeve = $this->rowSleeveMapper->find($row->getId());
			$this->updateMetaData($sleeve);
			$this->rowSleeveMapper->update($sleeve);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		$this->columnMapper->preloadColumns(array_column($changedCells, 'columnId'));

		// write all changed cells to its db-table
		foreach ($changedCells as $cell) {
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
		try {
			$column = $this->columnMapper->find($columnId);
		} catch (DoesNotExistException $e) {
			$this->logger->error('Can not insert cell, because the given column-id is not known', ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}

		// insert new cell
		$cellMapper = $this->getCellMapper($column);

		try {
			$cellClassName = 'OCA\Tables\Db\RowCell' . ucfirst($column->getType());
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
		$column = $this->columnMapper->find($columnId);
		$cellMapper = $this->getCellMapper($column);
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
				$this->updateCell($cell, $cellMapper, $value, $column);
			}
		} catch (DoesNotExistException) {
			$this->insertCell($rowId, $columnId, $value);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
	}

	private function getCellMapper(Column $column): RowCellMapperSuper {
		return $this->getCellMapperFromType($column->getType());
	}

	private function getCellMapperFromType(string $columnType): RowCellMapperSuper {
		$cellMapperClassName = 'OCA\Tables\Db\RowCell' . ucfirst($columnType) . 'Mapper';
		/** @var RowCellMapperSuper $cellMapper */
		try {
			return Server::get($cellMapperClassName);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
		}
	}

	/**
	 * @throws InternalError
	 */
	private function getColumnDbParamType(Column $column): int {
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
	 * @return int
	 */
	public function countRowsForView(View $view, string $userId): int {
		$filter = $view->getFilterArray();
		try {
			$this->columnMapper->preloadColumns($view->getColumnsArray(), $filter, $view->getSortArray());

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

	/**
	 * Sort rows array by the order specified in wantedRowIds
	 * @param Row2[] $rows
	 * @param int[] $wantedRowIds
	 * @return Row2[]
	 */
	private function sortRowsByIds(array $rows, array $wantedRowIds): array {
		// Create a map of row ID to row object for quick lookup
		$rowMap = [];
		foreach ($rows as $row) {
			$rowMap[$row->getId()] = $row;
		}

		// Build sorted array in the order specified by wantedRowIds
		$sortedRows = [];
		foreach ($wantedRowIds as $rowId) {
			if (isset($rowMap[$rowId])) {
				$sortedRows[] = $rowMap[$rowId];
			}
		}

		return $sortedRows;
	}
}
