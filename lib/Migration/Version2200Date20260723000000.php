<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version2200Date20260723000000 extends SimpleMigrationStep {
	private IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

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
		$qb = $this->connection->getQueryBuilder();
		$qb->update('tables_views')
			->set('technical_name', $qb->createFunction("CONCAT('view_', id)"))
			->where(
				$qb->expr()->orX(
					$qb->expr()->isNull('technical_name'),
					$qb->expr()->eq('technical_name', $qb->createNamedParameter('')),
				)
			);

		$updatedCount = $qb->executeStatement();

		$output->info('Version2200Date20260723000000: backfilled technical_name for ' . $updatedCount . ' views.');
	}
}
