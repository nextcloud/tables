<?php

declare(strict_types=1);

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20260315000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Add relation columns to tables_columns
		$columnsTable = $schema->getTable('tables_columns');
		if (!$columnsTable->hasColumn('relation_table_id')) {
			$columnsTable->addColumn('relation_table_id', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
			]);
		}
		if (!$columnsTable->hasColumn('relation_multiple')) {
			$columnsTable->addColumn('relation_multiple', Types::BOOLEAN, [
				'notnull' => false,
				'default' => false,
			]);
		}
		if (!$columnsTable->hasColumn('relation_target_column_id')) {
			$columnsTable->addColumn('relation_target_column_id', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
			]);
		}
		if (!$columnsTable->hasColumn('relation_type')) {
			$columnsTable->addColumn('relation_type', Types::STRING, [
				'notnull' => false,
				'length' => 20,
				'default' => 'many-to-many',
			]);
		}
		if (!$columnsTable->hasColumn('relation_display_column_id')) {
			$columnsTable->addColumn('relation_display_column_id', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
			]);
		}

		// Create join table for row relations
		if (!$schema->hasTable('tables_row_relations')) {
			$table = $schema->createTable('tables_row_relations');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('relation_column_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('source_row_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('target_row_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['relation_column_id', 'source_row_id', 'target_row_id'], 'row_rel_col_src_tgt');
			$table->addIndex(['source_row_id'], 'row_rel_source');
			$table->addIndex(['target_row_id'], 'row_rel_target');
			$table->addIndex(['relation_column_id'], 'row_rel_column');
		}

		// Drop the old cell table for relation type — relations now live in the join table
		if ($schema->hasTable('tables_row_cells_relation')) {
			$schema->dropTable('tables_row_cells_relation');
		}

		return $schema;
	}
}
