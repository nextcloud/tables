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

class Version001000Date20240712000000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('tables_tables')) {
			$table = $schema->getTable('tables_tables');
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
	}
}
