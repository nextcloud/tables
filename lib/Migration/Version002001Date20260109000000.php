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

class Version002001Date20260109000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$changes = $this->createRowValueTable($schema, 'relation', Types::INTEGER);
		return $changes;
	}

	private function createRowValueTable(ISchemaWrapper $schema, string $name, string $type): ?ISchemaWrapper {
		if (!$schema->hasTable('tables_row_cells_' . $name)) {
			$table = $schema->createTable('tables_row_cells_' . $name);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('column_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('row_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('value', $type, ['notnull' => false]);
			$table->addColumn('last_edit_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('last_edit_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addIndex(['column_id', 'row_id']);
			$table->addIndex(['column_id', 'value']);
			$table->setPrimaryKey(['id']);
			return $schema;
		}

		return null;
	}
}
