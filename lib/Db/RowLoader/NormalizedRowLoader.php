<?php

namespace OCA\Tables\Db\RowLoader;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\Row2;
use OCA\Tables\Db\RowCellMapperSuper;
use OCA\Tables\Db\RowSleeve;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Helper\ColumnsHelper;
use OCP\DB\Exception;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Loads rows using EAV structure (`tables_row_cells_*` tables)
 */
class NormalizedRowLoader implements RowLoader {
	public function __construct(
		private IDBConnection $db,
		private RowSleeveMapper $rowSleeveMapper,
		private ColumnsHelper $columnsHelper,
		private LoggerInterface $logger,
	) {
	}

	public function getRows(array $rowIds, array $columns, array $mappers): iterable {
		$maxParametersPerType = (int)floor(self::MAX_DB_PARAMETERS / count($this->columnsHelper->columns));
		$chunkSize = max(1, $maxParametersPerType - count($columns));

		foreach (array_chunk($rowIds, $chunkSize) as $chunkedRowIds) {
			yield from $this->getRowsChunk($chunkedRowIds, $columns, $mappers);
		}
	}

	/**
	 * Builds and executes the UNION ALL query for a specific chunk of rows.
	 * @param array $rowIds
	 * @param array<int, Column> $columns Column per columnId
	 * @param array<int, RowCellMapperSuper> $mappers Mapper per columnId
	 * @return Row2[]
	 * @throws InternalError
	 */
	private function getRowsChunk(array $rowIds, array $columns, array $mappers): array {
		$qb = $this->db->getQueryBuilder();

		$subqueries = [];
		foreach ($this->columnsHelper->columns as $columnType) {
			$qbValues = $this->db->getQueryBuilder();
			$qbValues->select('row_id', 'column_id', 'last_edit_at', 'last_edit_by')
				->selectAlias($qb->expr()->castColumn('value', IQueryBuilder::PARAM_STR), 'value');

			// This is not ideal but I cannot think of a good way to abstract this away into the mapper right now
			// Ideally we dynamically construct this query depending on what additional selects the column type requires
			// however the union requires us to match the exact number of selects for each column type
			if ($columnType === Column::TYPE_USERGROUP) {
				$qbValues->selectAlias($qb->expr()->castColumn('value_type', IQueryBuilder::PARAM_STR), 'value_type');
			} else {
				$qbValues->selectAlias($qbValues->createFunction('NULL'), 'value_type');
			}

			$qbValues
				->from('tables_row_cells_' . $columnType)
				->where($qb->expr()->in('column_id', $qb->createNamedParameter(array_keys($columns), IQueryBuilder::PARAM_INT_ARRAY, ':columnIds')))
				->andWhere($qb->expr()->in('row_id', $qb->createNamedParameter($rowIds, IQueryBuilder::PARAM_INT_ARRAY, ':rowsIds')));

			$subqueries[] = $qbValues->getSQL();
		}

		$qb->select('row_id', 'column_id', 'created_by', 'created_at', 't1.last_edit_by', 't1.last_edit_at', 'value', 'table_id')
			// Also should be more generic (see above)
			->addSelect('value_type')
			->from($qb->createFunction('(' . implode(' UNION ALL ', $subqueries) . ')'), 't1')
			->innerJoin('t1', 'tables_row_sleeves', 'rs', 'rs.id = t1.row_id');

		try {
			$result = $qb->executeQuery();
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), $e->getCode(), $e);
		}

		try {
			$sleeves = $this->rowSleeveMapper->findMultiple($rowIds);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), $e->getCode(), $e);
		}

		return $this->parseResult($result, $sleeves, $columns, $mappers);
	}

	/**
	 * @param IResult $result
	 * @param RowSleeve[] $sleeves
	 * @param array<int, Column> $columns Column per columnId
	 * @param array<int, RowCellMapperSuper> $mappers Mapper per columnId
	 * @return Row2[]
	 * @throws InternalError
	 */
	private function parseResult(IResult $result, array $sleeves, array $columns, array $mappers): array {
		$rows = [];
		foreach ($sleeves as $sleeve) {
			$id = (int)$sleeve['id'];
			$rows[$id] = new Row2();
			$rows[$id]->setId($id);
			$rows[$id]->setCreatedBy($sleeve['created_by']);
			$rows[$id]->setCreatedAt($sleeve['created_at']);
			$rows[$id]->setLastEditBy($sleeve['last_edit_by']);
			$rows[$id]->setLastEditAt($sleeve['last_edit_at']);
			$rows[$id]->setTableId((int)$sleeve['table_id']);
		}

		$rowValues = [];
		$keyToColumnId = [];
		$keyToRowId = [];

		while ($rowData = $result->fetch()) {
			if (!isset($rowData['row_id'], $rows[$rowData['row_id']])) {
				break;
			}

			$column = $columns[$rowData['column_id']];
			$cellMapper = $mappers[$rowData['column_id']];
			$value = $cellMapper->formatRowData($column, $rowData);
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

		return array_values($rows);
	}
}
