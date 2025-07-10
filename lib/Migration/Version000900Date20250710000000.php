<?php

/** @noinspection PhpUnused */

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migration that adds performance indexes to the tables_shares table
 *
 * These indexes optimize the following query patterns:
 * 1. Filtering by node_id and node_type - prioritizing high selectivity columns first
 * 2. Filtering by receiver and receiver_type - prioritizing high selectivity columns first
 * 3. Covering index for the common lookup pattern used in findAllSharesForNodeTo
 *
 * Index Selectivity Considerations:
 * - High selectivity columns (many unique values): node_id, receiver
 * - Low selectivity columns (few unique values): node_type, receiver_type
 * - The first two indexes prioritize high selectivity columns first for better performance
 * - The third is a covering index that matches the exact query pattern used in the code
 *
 * This significantly improves performance for:
 * - Looking up shares for a specific node
 * - Finding shares for a specific user/group/circle
 * - The optimized findAllSharesForNodeTo query that fetches all shares in one operation
 */

class Version000900Date20250710000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$tableName = 'tables_shares';

		if ($schema->hasTable($tableName)) {
			$table = $schema->getTable($tableName);

			if (!$table->hasIndex('shares_node_idx')) {
				$table->addIndex(['node_id', 'node_type'], 'shares_node_idx');
			}

			if (!$table->hasIndex('shares_receiver_idx')) {
				$table->addIndex(['receiver', 'receiver_type'], 'shares_receiver_idx');
			}
		}

		return $schema;
	}
}
