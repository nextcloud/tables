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

class Version2400Date20261117000000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$tableName = 'tables_tables';
		if (!$schema->hasTable($tableName)) {
			return null;
		}

		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('team_id')) {
			$table->addColumn('team_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}
		if (!$table->hasIndex('tables_tables_team_id')) {
			$table->addIndex(['team_id'], 'tables_tables_team_id');
		}

		return $schema;
	}
}
