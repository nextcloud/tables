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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

/**
 * Views get a type (table, grid) and may exist without a table.
 */
class Version2400Date20261012000000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('tables_views')) {
			$table = $schema->getTable('tables_views');
			if (!$table->hasColumn('type')) {
				$table->addColumn('type', Types::STRING, [
					'notnull' => true,
					'default' => 'table',
					'length' => 16,
				]);
			}
			// A grid view does not need a table
			$table->getColumn('table_id')->setNotnull(false);
		}

		return $schema;
	}
}
