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

class Version2011Date20260428000000 extends SimpleMigrationStep {
	private IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tables_views')) {
			return null;
		}

		$table = $schema->getTable('tables_views');
		if (!$table->hasColumn('technical_name')) {
			$table->addColumn('technical_name', Types::STRING, [
				'notnull' => false,
				'length' => 200,
			]);
		}

		if (!$table->hasIndex('tables_views_table_tech_name_uq')) {
			$table->addUniqueIndex(['table_id', 'technical_name'], 'tables_views_table_tech_name_uq');
		}

		return $schema;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$selectQb = $this->connection->getQueryBuilder();
		$selectQb->select('id')
			->from('tables_views')
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
				$viewId = (int)$row['id'];

				$updateQb = $this->connection->getQueryBuilder();
				$updateQb->update('tables_views')
					->set('technical_name', $updateQb->createNamedParameter('view_' . $viewId, IQueryBuilder::PARAM_STR))
					->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($viewId, IQueryBuilder::PARAM_INT)));

				$updatedCount += $updateQb->executeStatement();
			}
		} finally {
			$result->closeCursor();
		}

		$output->info('Version2011Date20260428000000: backfilled technical_name for ' . $updatedCount . ' views.');
	}
}
