<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000700Date20230916000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createRowSleevesTable($schema);

		$rowTypeSchema = [
			[
				'name' => 'text',
				'type' => Types::TEXT,
			],
			[
				'name' => 'number',
				'type' => Types::FLOAT,
			],
		] ;

		foreach ($rowTypeSchema as $colType) {
			$this->createRowValueTable($schema, $colType['name'], $colType['type']);
		}

		return $schema;
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
			// we will write this data to use it one day to extract versions of rows based on the timestamp
			$table->addColumn('last_edit_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('last_edit_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->setPrimaryKey(['id']);
		}
	}

	private function createRowSleevesTable(ISchemaWrapper $schema) {
		if (!$schema->hasTable('tables_row_sleeves')) {
			$table = $schema->createTable('tables_row_sleeves');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('table_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('last_edit_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('last_edit_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
		}
	}
}
