<?php

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;

interface IRowCellMapper {

	public function parseValueOutgoing(Column $column, $value);

	public function deleteAllForRow(int $rowId);

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function findByRowAndColumn(int $rowId, int $columnId): RowCellSuper;

	public function getTableName(): string;

	public function delete(Entity $entity): Entity;

	public function insert(Entity $entity): Entity;

	public function insertOrUpdate(Entity $entity): Entity;

	public function update(Entity $entity): Entity;

	public function find(int $id): RowCellSuper;

	public function updateWrapper(RowCellSuper $cell): RowCellSuper;
}
