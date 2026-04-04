<?php

declare(strict_types=1);

namespace OCA\Tables\Service;

use OCA\Tables\Db\Column;
use OCA\Tables\Db\RowRelation;
use OCA\Tables\Db\RowRelationMapper;

class RelationService {

	public function __construct(
		private RowRelationMapper $mapper,
	) {
	}

	/**
	 * Get linked target row IDs for a source row + column
	 */
	public function getLinkedRowIds(int $sourceRowId, int $columnId): array {
		$relations = $this->mapper->findBySourceRowAndColumn($sourceRowId, $columnId);
		return array_map(fn(RowRelation $r) => $r->getTargetRowId(), $relations);
	}

	/**
	 * Get reverse linked source row IDs for a target row + column
	 */
	public function getReverseLinkedRowIds(int $targetRowId, int $columnId): array {
		$relations = $this->mapper->findByTargetRowAndColumn($targetRowId, $columnId);
		return array_map(fn(RowRelation $r) => $r->getSourceRowId(), $relations);
	}

	/**
	 * Set the linked rows for a source row + column (replaces existing)
	 */
	public function setLinks(int $columnId, int $sourceRowId, array $targetRowIds, string $userId, Column $column): void {
		// Enforce relation type constraints
		$relationType = $column->getRelationType() ?? 'many-to-many';

		if ($relationType === 'one-to-one' && count($targetRowIds) > 1) {
			$targetRowIds = [array_shift($targetRowIds)];
		}

		// Get existing links
		$existing = $this->mapper->findBySourceRowAndColumn($sourceRowId, $columnId);
		$existingTargetIds = array_map(fn(RowRelation $r) => $r->getTargetRowId(), $existing);

		// Delete removed links
		foreach ($existing as $relation) {
			if (!in_array($relation->getTargetRowId(), $targetRowIds)) {
				$this->mapper->delete($relation);
			}
		}

		// Add new links
		$now = date('Y-m-d H:i:s');
		foreach ($targetRowIds as $targetRowId) {
			if (!in_array($targetRowId, $existingTargetIds)) {
				$relation = new RowRelation();
				$relation->setRelationColumnId($columnId);
				$relation->setSourceRowId($sourceRowId);
				$relation->setTargetRowId((int)$targetRowId);
				$relation->setCreatedBy($userId);
				$relation->setCreatedAt($now);
				$this->mapper->insert($relation);
			}
		}
	}

	/**
	 * Remove a specific link
	 */
	public function removeLink(int $columnId, int $sourceRowId, int $targetRowId): void {
		$this->mapper->deleteLink($columnId, $sourceRowId, $targetRowId);
	}

	/**
	 * Clean up all relations when a row is deleted
	 */
	public function onRowDeleted(int $rowId): void {
		$this->mapper->deleteBySourceRow($rowId);
		$this->mapper->deleteByTargetRow($rowId);
	}

	/**
	 * Clean up all relations when a column is deleted
	 */
	public function onColumnDeleted(int $columnId): void {
		$this->mapper->deleteByColumn($columnId);
	}
}
