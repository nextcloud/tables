<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class RowRelationMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tables_row_relations', RowRelation::class);
	}

	/**
	 * Get all relations for a given source row and column
	 */
	public function findBySourceRowAndColumn(int $sourceRowId, int $columnId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('source_row_id', $qb->createNamedParameter($sourceRowId)))
			->andWhere($qb->expr()->eq('relation_column_id', $qb->createNamedParameter($columnId)));
		return $this->findEntities($qb);
	}

	/**
	 * Get all relations for a given target row and column
	 */
	public function findByTargetRowAndColumn(int $targetRowId, int $columnId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('target_row_id', $qb->createNamedParameter($targetRowId)))
			->andWhere($qb->expr()->eq('relation_column_id', $qb->createNamedParameter($columnId)));
		return $this->findEntities($qb);
	}

	/**
	 * Get all relations for a column
	 */
	public function findByColumn(int $columnId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('relation_column_id', $qb->createNamedParameter($columnId)));
		return $this->findEntities($qb);
	}

	/**
	 * Delete all relations for a given source row
	 */
	public function deleteBySourceRow(int $sourceRowId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('source_row_id', $qb->createNamedParameter($sourceRowId)));
		$qb->executeStatement();
	}

	/**
	 * Delete all relations for a given target row
	 */
	public function deleteByTargetRow(int $targetRowId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('target_row_id', $qb->createNamedParameter($targetRowId)));
		$qb->executeStatement();
	}

	/**
	 * Delete all relations for a column (when column is deleted)
	 */
	public function deleteByColumn(int $columnId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('relation_column_id', $qb->createNamedParameter($columnId)));
		$qb->executeStatement();
	}

	/**
	 * Delete a specific relation link
	 */
	public function deleteLink(int $columnId, int $sourceRowId, int $targetRowId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('relation_column_id', $qb->createNamedParameter($columnId)))
			->andWhere($qb->expr()->eq('source_row_id', $qb->createNamedParameter($sourceRowId)))
			->andWhere($qb->expr()->eq('target_row_id', $qb->createNamedParameter($targetRowId)));
		$qb->executeStatement();
	}
}
