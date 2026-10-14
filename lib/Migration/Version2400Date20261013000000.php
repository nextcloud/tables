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
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * A view's uuid is unique on its own, also for views without a table: the index on
 * (table_id, uuid) does not hold when table_id is null, so it gives way to an index on uuid alone.
 * Imports and federated proxy views used to keep the uuid of their source, so views that share
 * one get a fresh uuid first; a local view keeps its own.
 */
class Version2400Date20261013000000 extends SimpleMigrationStep {
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	#[Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$select = $this->connection->getQueryBuilder();
		$select->select('id', 'uuid', 'external_id')
			->from('tables_views')
			->where($select->expr()->isNotNull('uuid'))
			->orderBy('id', 'ASC');
		$result = $select->executeQuery();
		$rows = [];
		while ($row = $result->fetchAssociative()) {
			$rows[] = $row;
		}
		$result->closeCursor();

		// A local view keeps its uuid; a federated proxy that copied it from its source gets a fresh one
		usort($rows, static fn (array $a, array $b): int => [$a['external_id'] !== null, (int)$a['id']] <=> [$b['external_id'] !== null, (int)$b['id']]);
		$seen = [];
		$duplicateIds = [];
		foreach ($rows as $row) {
			if (isset($seen[$row['uuid']])) {
				$duplicateIds[] = (int)$row['id'];
				continue;
			}
			$seen[$row['uuid']] = true;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('tables_views')
			->set('uuid', $update->createParameter('uuid'))
			->where($update->expr()->eq('id', $update->createParameter('id')));
		foreach ($duplicateIds as $id) {
			$update->setParameter('uuid', Uuid::v7()->toRfc4122());
			$update->setParameter('id', $id, IQueryBuilder::PARAM_INT);
			$update->executeStatement();
		}
		if ($duplicateIds !== []) {
			$output->info(count($duplicateIds) . ' views got a fresh uuid because another view already used theirs');
		}
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('tables_views')) {
			$table = $schema->getTable('tables_views');
			if (!$table->hasIndex('tables_views_uuid_only_uniq')) {
				$table->addUniqueIndex(['uuid'], 'tables_views_uuid_only_uniq');
			}
			// The uuid-only index covers the old (table_id, uuid) one
			if ($table->hasIndex('tables_views_uuid_uniq')) {
				$table->dropIndex('tables_views_uuid_uniq');
			}
		}

		return $schema;
	}
}
