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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260411000000 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $connection,
	) {
	}

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
					'notnull' => false,
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

			// Secondary index to support deleteAllForNode() / findAllForNode() queries
			// that filter on (node_type, node_id) without a leading user_id.
			$table->addIndex(['node_type', 'node_id'], 'archive_user_node_idx');
		}

		return $schema;
	}

	/**
	 * Migrate existing archived tables to per-user records.
	 *
	 * For every row in `tables_tables` where `archived = true`, insert one
	 * `tables_archive_user` record for the owner and one for each direct
	 * user-share recipient.
	 *
	 * Group and circle share recipients cannot be enumerated in a pure SQL
	 * migration. They inherit the archived state from the entity flag fallback
	 * on their first request after the migration.
	 *
	 * @param IOutput $output
	 * @param Closure $schemaClosure
	 * @param array $options
	 * @throws Exception
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$output->info('Migrating existing archived tables to per-user archive records...');

		$qb = $this->connection->getQueryBuilder();
		// Fetch all tables that are currently archived
		$qb->select('id', 'ownership')
			->from('tables_tables')
			->where($qb->expr()->eq('archived', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));

		$result = $qb->executeQuery();
		$archivedTables = $result->fetchAll();
		$result->closeCursor();

		if (empty($archivedTables)) {
			$output->info('No archived tables found, skipping data migration.');
			return;
		}

		$inserted = 0;

		foreach ($archivedTables as $tableRow) {
			$tableId = (int)$tableRow['id'];
			$ownerId = $tableRow['ownership'];

			// Insert owner record
			$inserted += $this->upsertArchiveRecord($ownerId, 0, $tableId, true);

			// Insert direct user-share recipient records
			$shareQb = $this->connection->getQueryBuilder();
			$shareQb->select('receiver')
				->from('tables_shares')
				->where($shareQb->expr()->eq('node_id', $shareQb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT)))
				->andWhere($shareQb->expr()->eq('node_type', $shareQb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
				->andWhere($shareQb->expr()->eq('receiver_type', $shareQb->createNamedParameter('user')));

			$shareResult = $shareQb->executeQuery();
			while ($shareRow = $shareResult->fetch()) {
				$receiverId = $shareRow['receiver'];
				if ($receiverId !== $ownerId) {
					$inserted += $this->upsertArchiveRecord($receiverId, 0, $tableId, true);
				}
			}
			$shareResult->closeCursor();
		}

		$output->info(sprintf('Inserted %d per-user archive records.', $inserted));
	}

	/**
	 * Insert a `tables_archive_user` record if it does not already exist.
	 * Returns 1 if a new record was inserted, 0 if it already existed.
	 *
	 * @throws Exception
	 */
	private function upsertArchiveRecord(string $userId, int $nodeType, int $nodeId, bool $archived): int {
		// Check for existing record first to avoid unique-index violation
		$checkQb = $this->connection->getQueryBuilder();
		$checkQb->select('id')
			->from('tables_archive_user')
			->where($checkQb->expr()->eq('user_id', $checkQb->createNamedParameter($userId)))
			->andWhere($checkQb->expr()->eq('node_type', $checkQb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT)))
			->andWhere($checkQb->expr()->eq('node_id', $checkQb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		$existing = $checkQb->executeQuery()->fetchOne();
		if ($existing !== false) {
			return 0;
		}

		$insertQb = $this->connection->getQueryBuilder();
		$insertQb->insert('tables_archive_user')
			->values([
				'user_id' => $insertQb->createNamedParameter($userId),
				'node_type' => $insertQb->createNamedParameter($nodeType, IQueryBuilder::PARAM_INT),
				'node_id' => $insertQb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT),
				'archived' => $insertQb->createNamedParameter($archived, IQueryBuilder::PARAM_BOOL),
			]);
		$insertQb->executeStatement();
		return 1;
	}
}
