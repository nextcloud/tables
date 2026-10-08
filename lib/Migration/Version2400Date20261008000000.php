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

/**
 * Views get a type (table, grid) and a grid layout, and may exist without
 * a table. Applications (contexts) get a technical name and a menu.
 */
class Version2400Date20261008000000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('tables_views')) {
			$table = $schema->getTable('tables_views');
			if (!$table->hasColumn('type')) {
				$table->addColumn('type', Types::STRING, [
					'notnull' => true,
					'default' => 'table',
					'length' => 16,
				]);
			}
			if (!$table->hasColumn('grid')) {
				$table->addColumn('grid', Types::TEXT, [
					'notnull' => false,
					'default' => null,
				]);
			}
			// A grid view does not need a table
			$table->getColumn('table_id')->setNotnull(false);
		}

		if ($schema->hasTable('tables_contexts_context')) {
			$table = $schema->getTable('tables_contexts_context');
			if (!$table->hasColumn('technical_name')) {
				$table->addColumn('technical_name', Types::STRING, [
					'notnull' => false,
					'default' => null,
					'length' => 200,
				]);
			}
		}

		if (!$schema->hasTable('tables_contexts_menu_item')) {
			$table = $schema->createTable('tables_contexts_menu_item');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('context_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('target_type', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('target_id', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000]);
			$table->addColumn('technical_name', Types::STRING, ['notnull' => false, 'length' => 200]);
			$table->addColumn('order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['context_id'], 'tables_ctx_menu_ctx_idx');
		}

		return $schema;
	}
}
