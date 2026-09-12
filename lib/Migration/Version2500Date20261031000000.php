<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2500Date20261031000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws Exception
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Step 1: Add `archived` column to `tables_contexts_context`
		// No index on the `archived` column: it is a low-cardinality boolean evaluated
		// after a higher-selectivity filter (ownership / join) is already applied.
		// Adding an index here would waste write overhead without measurable read benefit.
		if ($schema->hasTable('tables_contexts_context')) {
			$table = $schema->getTable('tables_contexts_context');
			if (!$table->hasColumn('archived')) {
				$table->addColumn('archived', Types::BOOLEAN, [
					'default' => false,
					'notnull' => true,
				]);
			}
		}

		// Step 2: Create `tables_archive_user` table for per-user archive overrides
		if (!$schema->hasTable('tables_archive_user')) {
			$table = $schema->createTable('tables_archive_user');
			$table->addColumn('id', Types::BIGINT, [
				'notnull' => true,
				'autoincrement' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('node_type', Types::SMALLINT, [
				'notnull' => true,
			]);
			$table->addColumn('node_id', Types::BIGINT, [
				'notnull' => true,
			]);
			// `archived` = true means the user archived this node;
			// `archived` = false means the user explicitly unarchived an owner-archived node.
			// No index on the `archived` column: it is a low-cardinality boolean evaluated
			// after a higher-selectivity filter (ownership / join) is already applied.
			// Adding an index here would waste write overhead without measurable read benefit.
			$table->addColumn('archived', Types::BOOLEAN, [
				'notnull' => true,
				'default' => true,
			]);
			$table->setPrimaryKey(['id']);

			// Unique index: one override row per (user, node_type, node_id) triple
			$table->addUniqueIndex(['user_id', 'node_type', 'node_id'], 'archive_user_unique_idx');

			// Secondary index to support deleteAllForNode() queries
			// that filter on (node_type, node_id) without a leading user_id.
			$table->addIndex(['node_type', 'node_id'], 'archive_user_node_idx');
		}

		return $schema;
	}
}
