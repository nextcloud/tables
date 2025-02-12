<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000800Date20240712000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$changes = $this->createUserGroupTable($schema, 'usergroup', Types::TEXT);
		$changes = $this->haveUserGroupColumnDefinitionFields($schema) ?? $changes;

		return $changes;
	}

	private function createUserGroupTable(ISchemaWrapper $schema, string $name, string $type): ?ISchemaWrapper {
		if (!$schema->hasTable('tables_row_cells_' . $name)) {
			$table = $schema->createTable('tables_row_cells_' . $name);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('column_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('row_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('value', $type, ['notnull' => false]);
			$table->addColumn('value_type', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('last_edit_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('last_edit_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addIndex(['column_id', 'row_id']);
			$table->setPrimaryKey(['id']);
			return $schema;
		}

		return null;
	}

	/**
	 * Add column schema options for usergroup type to the tables_columns table
	 */
	private function haveUserGroupColumnDefinitionFields(ISchemaWrapper $schema) {
		if ($schema->hasTable('tables_columns')) {
			$table = $schema->getTable('tables_columns');
			if (!$table->hasColumn('usergroup_default')) {
				$table->addColumn('usergroup_default', Types::TEXT, [
					'notnull' => false,
					'length' => 65535,
				]);
			}
			if (!$table->hasColumn('usergroup_multiple_items')) {
				$table->addColumn('usergroup_multiple_items', Types::BOOLEAN, [
					'notnull' => false,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('usergroup_select_users')) {
				$table->addColumn('usergroup_select_users', Types::BOOLEAN, [
					'notnull' => false,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('usergroup_select_groups')) {
				$table->addColumn('usergroup_select_groups', Types::BOOLEAN, [
					'notnull' => false,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('usergroup_select_teams')) {
				$table->addColumn('usergroup_select_teams', Types::BOOLEAN, [
					'notnull' => false,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('show_user_status')) {
				$table->addColumn('show_user_status', Types::BOOLEAN, [
					'notnull' => false,
					'default' => 0,
				]);
			}
			return $schema;
		}

		return null;
	}
}
