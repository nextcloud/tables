<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version2020Date20260513185340 extends SimpleMigrationStep {
	private const TARGET_TABLE_COLUMNS = 'tables_columns';
	private const TARGET_TABLE_VIEWS = 'tables_views';
	private const COL_ID = 'id';
	private const COL_UUID = 'uuid';
	private const COL_SELECTION_OPTIONS = 'selection_options';
	private const INDEX_NAME_COLUMNS = 'tables_col_uuid_uniq';
	private const INDEX_NAME_VIEWS = 'tables_views_uuid_uniq';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->addUuidColumnToTable($schema, self::TARGET_TABLE_COLUMNS, self::INDEX_NAME_COLUMNS);
		$this->addUuidColumnToTable($schema, self::TARGET_TABLE_VIEWS, self::INDEX_NAME_VIEWS);

		return $schema;
	}

	private function addUuidColumnToTable(ISchemaWrapper $schema, string $tableName, string $indexName): void {
		if (!$schema->hasTable($tableName)) {
			return;
		}
		$targetTable = $schema->getTable($tableName);
		if (!$targetTable->hasColumn(self::COL_UUID)) {
			$targetTable->addColumn(self::COL_UUID, Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 36,
				'comment' => 'UUIDv7 identifier to support structural updates across instances',
			]);
		}
		if (!$targetTable->hasUniqueConstraint($indexName)) {
			$targetTable->addUniqueIndex(['table_id', self::COL_UUID], $indexName);
		}
	}

	private function applyColumnOptionsUpdateIfNecessary(IQueryBuilder $query, int $columnId, ?string $rawSelectionOptions): void {
		$columnSelectionOptions = trim($rawSelectionOptions ?? '');
		if ($columnSelectionOptions === '') {
			return;
		}

		$selectionOptions = \json_decode($columnSelectionOptions, true);
		if (!is_array($selectionOptions) || empty($selectionOptions)) {
			return;
		}

		foreach ($selectionOptions as &$selectionOption) {
			if (!isset($selectionOption['uuid'])) {
				$selectionOption['uuid'] = Uuid::v7()->toRfc4122();
			}
		}

		$updatedSelectionOptions = json_encode($selectionOptions);
		unset($selectionOption);
		$query->setParameters(
			[
				'columnLocalId' => $columnId,
				'columnSelectionOptions' => $updatedSelectionOptions,
			],
			[
				Types::INTEGER,
				Types::TEXT,
			]
		);
		$query->executeStatement();
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->fillColumnUuids();
		$this->fillViewUuids();
	}

	private function fillColumnUuids(): void {
		$qbColUuidUpdate = $this->db->getQueryBuilder();
		$qbColUuidUpdate->update(self::TARGET_TABLE_COLUMNS)
			->set(self::COL_UUID, $qbColUuidUpdate->createParameter('columnUuid'))
			->where($qbColUuidUpdate->expr()->eq(self::COL_ID, $qbColUuidUpdate->createParameter('columnLocalId')));

		$qbColOptionsUuidUpdate = $this->db->getQueryBuilder();
		$qbColOptionsUuidUpdate->update(self::TARGET_TABLE_COLUMNS)
			->set(self::COL_SELECTION_OPTIONS, $qbColOptionsUuidUpdate->createParameter('columnSelectionOptions'))
			->where($qbColOptionsUuidUpdate->expr()->eq(self::COL_ID, $qbColOptionsUuidUpdate->createParameter('columnLocalId')));

		$qbSelect = $this->db->getQueryBuilder();
		$qbSelect->select(self::COL_ID, self::COL_SELECTION_OPTIONS)
			->from(self::TARGET_TABLE_COLUMNS);
		$select = $qbSelect->executeQuery();

		$writeBatches = 250;
		$updates = 0;

		try {
			$this->db->beginTransaction();
			while (($columnData = $select->fetchAssociative()) !== false) {
				$columnId = $columnData[self::COL_ID];
				$qbColUuidUpdate->setParameters(
					[
						'columnLocalId' => (int)$columnId,
						'columnUuid' => Uuid::v7()->toRfc4122(),
					],
					[
						Types::INTEGER,
						Types::STRING,
					]
				);
				$qbColUuidUpdate->executeStatement();

				$this->applyColumnOptionsUpdateIfNecessary($qbColOptionsUuidUpdate, (int)$columnId, $columnData[self::COL_SELECTION_OPTIONS]);

				$updates++;
				if ($updates % $writeBatches === 0) {
					$this->db->commit();
					$this->db->beginTransaction();
				}
			}
			$this->db->commit();
		} catch (\Exception $e) {
			$this->db->rollBack();
			throw $e;
		}

		$select->closeCursor();
	}

	private function fillViewUuids(): void {
		$qbColUuidUpdate = $this->db->getQueryBuilder();
		$qbColUuidUpdate->update(self::TARGET_TABLE_VIEWS)
			->set(self::COL_UUID, $qbColUuidUpdate->createParameter('columnUuid'))
			->where($qbColUuidUpdate->expr()->eq(self::COL_ID, $qbColUuidUpdate->createParameter('columnLocalId')));

		$qbSelect = $this->db->getQueryBuilder();
		$qbSelect->select(self::COL_ID)
			->from(self::TARGET_TABLE_VIEWS);
		$select = $qbSelect->executeQuery();

		$writeBatches = 250;
		$updates = 0;

		try {
			$this->db->beginTransaction();
			while (($columnData = $select->fetchAssociative()) !== false) {
				$columnId = $columnData[self::COL_ID];
				$qbColUuidUpdate->setParameters(
					[
						'columnLocalId' => (int)$columnId,
						'columnUuid' => Uuid::v7()->toRfc4122(),
					],
					[
						Types::INTEGER,
						Types::STRING,
					]
				);
				$qbColUuidUpdate->executeStatement();

				$updates++;
				if ($updates % $writeBatches === 0) {
					$this->db->commit();
					$this->db->beginTransaction();
				}
			}
			$this->db->commit();
		} catch (\Exception $e) {
			$this->db->rollBack();
			throw $e;
		}

		$select->closeCursor();
	}
}
