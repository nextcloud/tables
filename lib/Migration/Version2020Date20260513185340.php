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
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version2020Date20260513185340 extends SimpleMigrationStep {
	private const TARGET_TABLE = 'tables_columns';
	private const COL_ID = 'id';
	private const COL_UUID = 'uuid';
	private const INDEX_NAME = 'tables_col_uuid_uniq';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable(self::TARGET_TABLE)) {
			return null;
		}

		$columnsTable = $schema->getTable(self::TARGET_TABLE);
		if (!$columnsTable->hasColumn(self::COL_UUID)) {
			$columnsTable->addColumn(self::COL_UUID, Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 36,
				'comment' => 'UUIDv7 identifier to support structural updates across instances',
			]);
		}
		if (!$columnsTable->hasUniqueConstraint(self::INDEX_NAME)) {
			$columnsTable->addUniqueIndex(['table_id', self::COL_UUID], self::INDEX_NAME);
		}

		return $schema;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {

		$qbUpdate = $this->db->getQueryBuilder();
		$qbUpdate->update(self::TARGET_TABLE)
			->set(self::COL_UUID, $qbUpdate->createParameter('columnUuid'))
			->where($qbUpdate->expr()->eq(self::COL_ID, $qbUpdate->createParameter('columnLocalId')));

		$qbSelect = $this->db->getQueryBuilder();
		$qbSelect->select(self::COL_ID)
			->from(self::TARGET_TABLE);
		$select = $qbSelect->executeQuery();

		$writeBatches = 250;
		$updates = 0;

		try {
			$this->db->beginTransaction();
			while (($columnId = $select->fetchOne()) !== false) {
				$qbUpdate->setParameters(
					[
						'columnLocalId' => (int)$columnId,
						'columnUuid' => Uuid::v7()->toRfc4122(),
					],
					[
						Types::INTEGER,
						Types::STRING,
					]
				);
				$qbUpdate->executeStatement();
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
