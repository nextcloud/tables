<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version1000Date20260327000000 extends SimpleMigrationStep {
	private IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tables_columns')) {
			return null;
		}

		$table = $schema->getTable('tables_columns');
		if (!$table->hasColumn('technical_name')) {
			$table->addColumn('technical_name', Types::STRING, [
				'notnull' => false,
				'length' => 200,
			]);
		}

		if (!$table->hasIndex('tables_columns_table_tech_name_uq')) {
			$table->addUniqueIndex(['table_id', 'technical_name'], 'tables_columns_table_tech_name_uq');
		}

		return $schema;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$selectQb = $this->connection->getQueryBuilder();
		$selectQb->select('id')
			->from('tables_columns')
			->where(
				$selectQb->expr()->orX(
					$selectQb->expr()->isNull('technical_name'),
					$selectQb->expr()->eq('technical_name', $selectQb->createNamedParameter('')),
				)
			);

		$result = $selectQb->executeQuery();
		$updatedCount = 0;

		try {
			while ($row = $result->fetchAssociative()) {
				$columnId = (int)$row['id'];

				$updateQb = $this->connection->getQueryBuilder();
				$updateQb->update('tables_columns')
					->set('technical_name', $updateQb->createNamedParameter('column_' . $columnId, IQueryBuilder::PARAM_STR))
					->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($columnId, IQueryBuilder::PARAM_INT)));

				$updatedCount += $updateQb->executeStatement();
			}
		} finally {
			$result->closeCursor();
		}

		$output->info('Version1000Date20260327000000: backfilled technical_name for ' . $updatedCount . ' columns.');
	}
}
