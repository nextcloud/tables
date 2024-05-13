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

class Version000700Date20230916000000 extends SimpleMigrationStep {

	/**
	 * this is a copy from the definition set in OCA\Tables\Helper\ColumnsHelper with added types
	 * the names have to be in sync! but the definition can not be used directly
	 * because it might cause problems on auto web updates
	 * (class might not be loaded if it gets replaced during the runtime)
	 */
	private array $columns = [
		[
			'name' => 'text',
			'db_type' => Types::TEXT,
		],
		[
			'name' => 'number',
			'db_type' => Types::FLOAT,
		],
		[
			'name' => 'datetime',
			'db_type' => Types::TEXT,
		],
		[
			'name' => 'selection',
			'db_type' => Types::TEXT,
		],
		[
			'name' => 'usergroup',
			'db_type' => Types::TEXT,
		],
	];

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

		$this->createRowSleevesTable($schema);

		$rowTypeSchema = $this->columns;

		foreach ($rowTypeSchema as $colType) {
			$this->createRowValueTable($schema, $colType['name'], $colType['db_type']);
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
			$table->addIndex(['column_id', 'row_id']);
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
			$table->addIndex(['id']);
			$table->setPrimaryKey(['id']);
		}
	}
}
