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
 * Applications (contexts) get a menu. A menu item's technical name names its page and is
 * unique within the application; the index keeps it so when two saves run at once.
 */
class Version2400Date20261012020000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tables_contexts_menu_item')) {
			$table = $schema->createTable('tables_contexts_menu_item');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('context_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
			$table->addColumn('target_type', Types::STRING, ['notnull' => true, 'length' => 16]);
			$table->addColumn('target_id', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000]);
			$table->addColumn('technical_name', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
		}

		$table = $schema->getTable('tables_contexts_menu_item');
		if (!$table->hasIndex('tables_ctx_menu_name_uq')) {
			$table->addUniqueIndex(['context_id', 'technical_name'], 'tables_ctx_menu_name_uq');
		}

		return $schema;
	}
}
