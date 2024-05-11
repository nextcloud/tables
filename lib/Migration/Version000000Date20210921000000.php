<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace OCA\Tables\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000000Date20210921000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = 'tables_tables';
		if (!$schema->hasTable($table)) {
			$table = $schema->createTable($table);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('ownership', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('last_edit_by', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('last_edit_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('description', Types::TEXT, [
				'default' => '',
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		$table = 'tables_columns';
		if (!$schema->hasTable($table)) {
			$table = $schema->createTable($table);

			// general
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('table_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('last_edit_by', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('last_edit_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('subtype', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('mandatory', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('order_weight', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			// type text
			$table->addColumn('text_default', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('text_allowed_pattern', Types::STRING, [
				'notnull' => false,
				'length' => 200,
			]);
			$table->addColumn('text_max_length', Types::INTEGER, [
				'notnull' => false,
			]);

			// type number
			$table->addColumn('number_default', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('number_min', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('number_max', Types::FLOAT, [
				'notnull' => false,
			]);
			$table->addColumn('number_decimals', Types::INTEGER, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('number_prefix', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('number_suffix', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);

			// type selection
			$table->addColumn('selection_options', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('selection_default', Types::TEXT, [
				'notnull' => false,
			]);

			// type datetime
			$table->addColumn('datetime_default', Types::STRING, [
				'notnull' => false,
			]);

			// type usergroup
			$table->addColumn('usergroup_default', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('usergroup_multiple_items', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('usergroup_select_users', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('usergroup_select_groups', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('show_user_status', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id']);
		}

		$table = 'tables_rows';
		if (!$schema->hasTable($table)) {
			$table = $schema->createTable($table);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('table_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('last_edit_by', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('last_edit_at', Types::DATETIME, [
				'notnull' => true,
			]);
			// json is not official supported by nextcloud, but DBAL can run that
			// due to db requirements, this should be supported
			$table->addColumn('data', \Doctrine\DBAL\Types\Types::JSON, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		$table = 'tables_log';
		if (!$schema->hasTable($table)) {
			$table = $schema->createTable($table);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('time', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('action_type', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('action_data', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('trigger_type', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->addColumn('data_type', Types::STRING, [
				'notnull' => true,
				'length' => 200,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}
}
