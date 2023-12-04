<?php

namespace OCA\Tables\Db;

use DateTime;
use OCA\Tables\Errors\InternalError;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Helper\UserHelper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

class Row2Mapper {
	private RowSleeveMapper $rowSleeveMapper;
	private ?string $userId = null;
	private IDBConnection $db;
	private LoggerInterface $logger;
	protected UserHelper $userHelper;

	/* @var Column[] $columns */
	private array $columns = [];

	public function __construct(?string $userId, IDBConnection $db, LoggerInterface $logger, UserHelper $userHelper, RowSleeveMapper $rowSleeveMapper) {
		$this->rowSleeveMapper = $rowSleeveMapper;
		$this->userId = $userId;
		$this->db = $db;
		$this->logger = $logger;
		$this->userHelper = $userHelper;
	}

	/**
	 * @throws InternalError
	 */
	public function delete(Row2 $row): Row2 {
		foreach (['text', 'number'] as $columnType) {
			$cellMapperClassName = 'OCA\Tables\Db\RowCell' . ucfirst($columnType) . 'Mapper';
			/** @var RowCellMapperSuper $cellMapper */
			try {
				$cellMapper = Server::get($cellMapperClassName);
			} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage());
			}
			try {
				$cellMapper->deleteAllForRow($row->getId());
			} catch (Exception $e) {
				$this->logger->error($e->getMessage(), ['exception' => $e]);
				throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
			}
		}
		try {
			$this->rowSleeveMapper->deleteById($row->getId());
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
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
			throw new NotFoundError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		} else {
			$e = new Exception('Too many results for one wanted row.');
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getTableIdForRow(int $rowId): int {
		$rowSleeve = $this->rowSleeveMapper->find($rowId);
		return $rowSleeve->getTableId();
	}

	/**
	 * @param string $userId
	 * @param array|null $filter
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return int[]
	 * @throws InternalError
	 */
	private function getWantedRowIds(string $userId, ?array $filter = null, ?int $limit = null, ?int $offset = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from('tables_row_sleeves', 'sleeves');
		// ->where($qb->expr()->eq('table_id', $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)));
		if($filter) {
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
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return Row2[]
	 * @throws InternalError
	 */
	public function findAll(array $columns, int $limit = null, int $offset = null, array $filter = null, array $sort = null, string $userId = null): array {
		$this->setColumns($columns);
		$columnIdsArray = array_map(fn (Column $column) => $column->getId(), $columns);

		$wantedRowIdsArray = $this->getWantedRowIds($userId, $filter, $limit, $offset);

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

		$qbText = $this->db->getQueryBuilder();
		$qbText->select('*')
			->from('tables_row_cells_text')
			->where($qb->expr()->in('column_id', $qb->createNamedParameter($columnIds, IQueryBuilder::PARAM_INT_ARRAY, ':columnIds')))
			->andWhere($qb->expr()->in('row_id', $qb->createNamedParameter($rowIds, IQueryBuilder::PARAM_INT_ARRAY, ':rowsIds')));

		$qbNumber = $this->db->getQueryBuilder();
		$qbNumber->select('*')
			->from('tables_row_cells_number')
			->where($qb->expr()->in('column_id', $qb->createNamedParameter($columnIds, IQueryBuilder::PARAM_INT_ARRAY, ':columnIds')))
			->andWhere($qb->expr()->in('row_id', $qb->createNamedParameter($rowIds, IQueryBuilder::PARAM_INT_ARRAY, ':rowIds')));

		$qb->select('row_id', 'column_id', 'created_by', 'created_at', 't1.last_edit_by', 't1.last_edit_at', 'value', 'table_id')
			->from($qb->createFunction('((' . $qbText->getSQL() . ') UNION ALL (' . $qbNumber->getSQL() . '))'), 't1')
			->innerJoin('t1', 'tables_row_sleeves', 'rowSleeve', 'rowSleeve.id = t1.row_id');

		try {
			$result = $this->db->executeQuery($qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes());
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': ' . $e->getMessage(), );
		}

		return $this->parseEntities($result);
	}

	/**
	 * @throws InternalError
	 */
	private function addFilterToQuery(IQueryBuilder &$qb, array $filters, string $userId): void {
		// TODO move this into service
		$this->replaceMagicValues($filters, $userId);

		if (count($filters) > 0) {
			$qb->andWhere(
				$qb->expr()->orX(
					...$this->getFilterGroups($qb, $filters)
				)
			);
		}
	}

	private function replaceMagicValues(array &$filters, string $userId): void {
		foreach ($filters as &$filterGroup) {
			foreach ($filterGroup as &$filter) {
				if(str_starts_with($filter['value'], '@')) {
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
			$tmp = $this->getFilter($qb, $filterGroup);
			$filterGroups[] = $qb->expr()->andX(...$tmp);
		}
		return $filterGroups;
	}

	/**
	 * @throws InternalError
	 */
	private function getFilter(IQueryBuilder &$qb, array $filterGroup): array {
		$filterExpressions = [];
		foreach ($filterGroup as $filter) {
			$sql = $qb->expr()->in(
				'id',
				$qb->createFunction($this->getFilterExpression($qb, $this->columns[$filter['columnId']], $filter['operator'], $filter['value'])->getSQL())
			);
			$filterExpressions[] = $sql;
		}
		return $filterExpressions;
	}

	/**
	 * @throws InternalError
	 */
	private function getFilterExpression(IQueryBuilder $qb, Column $column, string $operator, string $value): IQueryBuilder {
		if($column->getType() === 'number' && $column->getNumberDecimals() === 0) {
			$paramType = IQueryBuilder::PARAM_INT;
			$value = (int)$value;
		} elseif ($column->getType() === 'datetime') {
			$paramType = IQueryBuilder::PARAM_DATE;
		} else {
			$paramType = IQueryBuilder::PARAM_STR;
		}

		$qb2 = $this->db->getQueryBuilder();
		$qb2->select('row_id');
		$qb2->where($qb->expr()->eq('column_id', $qb->createNamedParameter($column->getId()), IQueryBuilder::PARAM_INT));

		switch($column->getType()) {
			case 'text':
				$qb2->from('tables_row_cells_text');
				break;
			case 'number':
				$qb2->from('tables_row_cells_number');
				break;
			default:
				throw new InternalError('column type unknown to match cell-table for it');
		}


		switch ($operator) {
			case 'begins-with':
				return $qb2->andWhere($qb->expr()->like('value', $qb->createNamedParameter('%'.$value, $paramType)));
			case 'ends-with':
				return $qb2->andWhere($qb->expr()->like('value', $qb->createNamedParameter($value.'%', $paramType)));
			case 'contains':
				return $qb2->andWhere($qb->expr()->like('value', $qb->createNamedParameter('%'.$value.'%', $paramType)));
			case 'is-equal':
				return $qb2->andWhere($qb->expr()->eq('value', $qb->createNamedParameter($value, $paramType)));
			case 'is-greater-than':
				return $qb2->andWhere($qb->expr()->gt('value', $qb->createNamedParameter($value, $paramType)));
			case 'is-greater-than-or-equal':
				return $qb2->andWhere($qb->expr()->gte('value', $qb->createNamedParameter($value, $paramType)));
			case 'is-lower-than':
				return $qb2->andWhere($qb->expr()->lt('value', $qb->createNamedParameter($value, $paramType)));
			case 'is-lower-than-or-equal':
				return $qb2->andWhere($qb->expr()->lte('value', $qb->createNamedParameter($value, $paramType)));
			case 'is-empty':
				return $qb2->andWhere($qb->expr()->isNull('value'));
			default:
				throw new InternalError('Operator '.$operator.' is not supported.');
		}
	}

	/** @noinspection DuplicatedCode */
	private function resolveSearchValue(string $magicValue, string $userId): string {
		switch (ltrim($magicValue, '@')) {
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
			default: return $magicValue;
		}
	}

	/**
	 * @param IResult $result
	 * @return Row2[]
	 * @throws InternalError
	 */
	private function parseEntities(IResult $result): array {
		$data = $result->fetchAll();

		$rows = [];
		foreach ($data as $rowData) {
			$this->parseModel($rowData, $rows[$rowData['row_id']]);
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
		return in_array($rowId, $this->getWantedRowIds($userId, $view->getFilterArray()));
	}

	/**
	 * @param IResult $result
	 * @return Row2
	 * @throws InternalError
	 */
	private function parseEntity(IResult $result): Row2 {
		$data = $result->fetchAll();

		if(count($data) === 0) {
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': result was empty, expected one row');
		}
		if(count($data) > 1) {
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': found more than one expected result');
		}

		return $this->parseModel($data[0]);
	}


	/**
	 * @param Row2 $row
	 * @param column[] $columns
	 * @return Row2
	 * @throws InternalError
	 * @throws Exception
	 */
	public function insert(Row2 $row, array $columns): Row2 {
		if(!$columns || count($columns) === 0) {
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': columns are missing');
		}
		$this->setColumns($columns);

		// create a new row sleeve to get a new rowId
		$rowSleeve = $this->createNewRowSleeve($row->getTableId());
		$row->setId($rowSleeve->getId());

		// write all cells to its db-table
		foreach ($row->getData() as $cell) {
			$this->insertCell($rowSleeve->getId(), $cell['columnId'], $cell['value']);
		}

		return $row;
	}

	/**
	 * @throws InternalError
	 */
	public function update(Row2 $row, array $columns): Row2 {
		if(!$columns || count($columns) === 0) {
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
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
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
	 * Updates the last_edit_by and last_edit_at data
	 * optional adds the created_by and created_at data
	 *
	 * @param RowSleeve|IRowCell $entity
	 * @param bool $setCreate
	 * @return void
	 */
	private function updateMetaData($entity, bool $setCreate = false): void {
		$time = new DateTime();
		if ($setCreate) {
			$entity->setCreatedBy($this->userId);
			$entity->setCreatedAt($time->format('Y-m-d H:i:s'));
		}
		$entity->setLastEditBy($this->userId);
		$entity->setLastEditAt($time->format('Y-m-d H:i:s'));
	}

	/**
	 * Insert a cell to its specific db-table
	 *
	 * @throws InternalError
	 */
	private function insertCell(int $rowId, int $columnId, string $value): void {
		$cellClassName = 'OCA\Tables\Db\RowCell'.ucfirst($this->columns[$columnId]->getType());
		/** @var IRowCell $cell */
		$cell = new $cellClassName();

		$cell->setRowIdWrapper($rowId);
		$cell->setColumnIdWrapper($columnId);
		$cell->setValueWrapper($value);
		$this->updateMetaData($cell);

		// insert new cell
		$cellMapperClassName = 'OCA\Tables\Db\RowCell'.ucfirst($this->columns[$columnId]->getType()).'Mapper';
		/** @var QBMapper $cellMapper */
		try {
			$cellMapper = Server::get($cellMapperClassName);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		try {
			$cellMapper->insert($cell);
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
	}

	/**
	 * @param IRowCell $cell
	 * @param IRowCellMapper $mapper
	 * @param mixed $value the value should be parsed to the correct format within the row service
	 */
	private function updateCell(IRowCell $cell, IRowCellMapper $mapper, $value): void {
		$cell->setValueWrapper($value);
		$this->updateMetaData($cell);
		$mapper->updateWrapper($cell);
	}

	/**
	 * @throws InternalError
	 */
	private function insertOrUpdateCell(int $rowId, int $columnId, string $value): void {
		$cellMapperClassName = 'OCA\Tables\Db\RowCell'.ucfirst($this->columns[$columnId]->getType()).'Mapper';
		/** @var IRowCellMapper $cellMapper */
		try {
			$cellMapper = Server::get($cellMapperClassName);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		try {
			$cell = $cellMapper->findByRowAndColumn($rowId, $columnId);
			$this->updateCell($cell, $cellMapper, $value);
		} catch (DoesNotExistException $e) {
			$this->insertCell($rowId, $columnId, $value);
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
	}

	/**
	 * @param Column[] $columns
	 */
	private function setColumns(array $columns): void {
		foreach ($columns as $column) {
			$this->columns[$column->getId()] = $column;
		}
	}

	/**
	 * @throws InternalError
	 */
	private function formatValue(Column $column, string $value) {
		$cellMapperClassName = 'OCA\Tables\Db\RowCell'.ucfirst($column->getType()).'Mapper';
		/** @var RowCellMapperSuper $cellMapper */
		try {
			$cellMapper = Server::get($cellMapperClassName);
		} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new InternalError(get_class($this) . ' - ' . __FUNCTION__ . ': '.$e->getMessage());
		}
		return $cellMapper->parseValueOutgoing($column, $value);
	}

	/**
	 * @param array $data
	 * @param Row2|null $row
	 * @return Row2
	 * @throws InternalError
	 */
	private function parseModel(array $data, ?Row2 &$row = null): Row2 {
		if (!$row) {
			$row = new Row2();
			$row->setId($data['row_id']);
			$row->setTableId($data['table_id']);
			$row->setCreatedBy($data['created_by']);
			$row->setCreatedAt($data['created_at']);
			$row->setLastEditBy($data['last_edit_by']);
			$row->setLastEditAt($data['last_edit_at']);
		}
		$row->addCell($data['column_id'], $this->formatValue($this->columns[$data['column_id']], $data['value']));
		return $row;
	}

}
