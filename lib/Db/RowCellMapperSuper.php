<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use phpDocumentor\Reflection\Types\ClassString;

/**
 * @template-extends QBMapper<T>
 * @template T of RowCellSuper
 */
class RowCellMapperSuper extends QBMapper implements IRowCellMapper {

	public function __construct(IDBConnection $db, string $table, ClassString $class) {
		parent::__construct($db, $table, $class);
	}

	/**
	 * @param Column $column
	 * @param mixed $value
	 * @return mixed
	 */
	public function parseValueOutgoing(Column $column, $value) {
		return $value;
	}

	/**
	 * @throws Exception
	 */
	public function deleteAllForRow(int $rowId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('row_id', $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function findByRowAndColumn(int $rowId, int $columnId): RowCellSuper {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('row_id', $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('column_id', $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)));
		$item = $this->findEntity($qb);
		return $item;
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function find(int $id): RowCellSuper {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @psalm-param RowCellSuper $cell
	 * @throws Exception
	 */
	public function updateWrapper(RowCellSuper $cell): RowCellSuper	{
		// TODO is this possible?
		$cell = $this->update($cell);
		return $cell;
	}

}
