<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<T>
 * @template T of RowCellSuper
 * @template TIncoming Type the db is using to store the actual value
 * @template TOutgoing Type the API is using
 */
class RowCellMapperSuper extends QBMapper {

	public function __construct(IDBConnection $db, string $table, string $class) {
		parent::__construct($db, $table, $class);
	}

	/**
	 * Format a row cell entity to API response array
	 *
	 * @param T $cell
	 * @return TOutgoing
	 */
	public function formatEntity(Column $column, RowCellSuper $cell) {
		/** @var TOutgoing $value */
		$value = $cell->getValue();
		return $value;
	}
	/*
	 * Transform value from a filter rule to the actual query parameter used
	 * for constructing the view filter query
	 */
	public function filterValueToQueryParam(Column $column, mixed $value): mixed {
		return $value;
	}

	public function applyDataToEntity(Column $column, RowCellSuper $cell, $data): void {
		$cell->setValue($data);
	}

	public function getDbParamType() {
		return IQueryBuilder::PARAM_STR;
	}

	/*
	 * Indicating that the column can have multiple values represented by individual entities
	 */
	public function hasMultipleValues(): bool {
		return false;
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
	 * @throws Exception
	 */
	public function deleteAllForColumnAndRow(int $columnId, int $rowId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('column_id', $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('row_id', $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}


	/**
	 * @throws Exception
	 */
	public function deleteAllForColumn(int $columnId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('column_id', $qb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT))
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
		return $this->findEntity($qb);
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
	 * @psalm-param T $cell
	 * @psalm-return T
	 * @throws Exception
	 */
	public function updateWrapper(RowCellSuper $cell): RowCellSuper {
		return $this->update($cell);
	}

}
