<?php

declare(strict_types=1);

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20260314000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('tables_views');
		if (!$table->hasColumn('type')) {
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 20,
				'default' => 'table',
			]);
		}

		return $schema;
	}
}
