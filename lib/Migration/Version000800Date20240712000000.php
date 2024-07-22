<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000800Date20240712000000 extends SimpleMigrationStep {
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
		$this->createRowValueTable($schema, 'usergroup', Types::TEXT);

		if ($schema->hasTable('tables_columns')) {
			$table = $schema->getTable('tables_columns');
			if (!$table->hasColumn('usergroup_default')) {
				$table->addColumn('usergroup_default', Types::TEXT, [
					'notnull' => false,
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

	private function createRowValueTable(ISchemaWrapper $schema, string $name, string $type) {
		if (!$schema->hasTable('tables_row_cells_'.$name)) {
			$table = $schema->createTable('tables_row_cells_'.$name);
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
		}
	}
}
