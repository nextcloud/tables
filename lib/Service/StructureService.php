<?php
declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service;

class StructureService {

	protected array $addedColumns = [];
	protected array $removedColumns = [];
	protected array $modifiedColumns = [];

	public function resolveChanges(array $currentSchema, array $updateSchema): void {
		$this->resolveColumnChanges($currentSchema, $updateSchema);
	}

	protected function resolveColumnChanges(array $currentSchema, array $updateSchema): void {
		$existingColumnMap = $this->getColumnMap($currentSchema['columns']);
		$updatedColumnMap = $this->getColumnMap($updateSchema['columns']);

		$this->determineAddedColumns($existingColumnMap, $updatedColumnMap, $updateSchema);
		$this->determineRemovedColumns($existingColumnMap, $updatedColumnMap, $currentSchema);
		$this->determineModifiedColumns($existingColumnMap, $updatedColumnMap, $currentSchema, $updateSchema);
	}

	protected function determineAddedColumns(array $existingColumnMap, array $updatedColumnMap, array $updateSchema): void {
		$columnUuids = array_diff_key($updatedColumnMap, $existingColumnMap);
		foreach ($columnUuids as $uuid) {
			$this->addedColumns[$uuid] = $updateSchema['columns'][$updatedColumnMap[$uuid]['arrayIndex']];
		}
	}

	protected function determineRemovedColumns(array $existingColumnMap, array $updatedColumnMap, array $currentSchema): void {
		$columnUuids = array_diff_key($existingColumnMap, $updatedColumnMap);
		foreach ($columnUuids as $uuid) {
			$this->removedColumns[$uuid] = $currentSchema['columns'][$existingColumnMap[$uuid]['arrayIndex']];
		}
	}

	protected function determineModifiedColumns(array $existingColumnMap, array $updatedColumnMap, array $currentSchema, array $updateSchema): void {
		$updatedColumnUuids = array_intersect_key($existingColumnMap, $updatedColumnMap);

		$updatedColumnUuids = array_filter(
			$updatedColumnUuids,
			function (array $uuids, string $uuid) use ($currentSchema, $updateSchema, $existingColumnMap, $updatedColumnMap): bool {
				$columnCurrent = $currentSchema['columns'][$existingColumnMap[$uuid]['arrayIndex']];
				$columnNew = $updateSchema['columns'][$updatedColumnMap[$uuid]['arrayIndex']];
				return $this->isColumnSame($columnNew, $columnCurrent);
			}
		);

		foreach ($updatedColumnUuids as $uuid) {
			$this->modifiedColumns[$uuid] = [
				'from' => $currentSchema['columns'][$existingColumnMap[$uuid]['arrayIndex']],
				'to' => $updateSchema['columns'][$updatedColumnMap[$uuid]['arrayIndex']],
			];
		}
	}

	protected function isColumnSame($columnA, $columnB): bool {
		unset(
			$columnA['id'], $columnB['id'],
			$columnA['createdBy'], $columnB['createdBy'],
			$columnA['createdByDisplayName'], $columnB['createdByDisplayName'],
			$columnA['createdAt'], $columnB['createdAt'],
			$columnA['lastEditBy'], $columnB['lastEditBy'],
			$columnA['lastEditByDisplayName'], $columnB['lastEditByDisplayName'],
			$columnA['lastEditAt'], $columnB['lastEditAt'],
			$columnA['tableId'], $columnB['tableId'],
		);
		return array_diff_assoc($columnA, $columnB) !== [];
	}

	protected function getColumnMap(array $currentSchemaColumns): array {
		$map = [];
		foreach ($currentSchemaColumns as $i => $column) {
			$map[$column['uuid']] = [
				'arrayIndex' => $i,
				'id' => $column['id'],
			];
		}
		return $map;
	}
}
