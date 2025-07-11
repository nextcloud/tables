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
