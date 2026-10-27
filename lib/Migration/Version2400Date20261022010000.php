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
 * The menu of an application: items that open a view, a table, a page of the
 * application or a link, one level deep. A technical name is unique within the
 * application.
 */
class Version2400Date20261022010000 extends SimpleMigrationStep {
	private const TABLE = 'tables_contexts_menu_item';

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable(self::TABLE)) {
			return null;
		}

		$table = $schema->createTable(self::TABLE);
		$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('context_id', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 200]);
		$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		$table->addColumn('target_type', Types::STRING, ['notnull' => true, 'length' => 16]);
		$table->addColumn('target_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000]);
		// Nullable so an item can be inserted first and named `item_<id>` after.
		$table->addColumn('technical_name', Types::STRING, ['notnull' => false, 'length' => 200]);
		$table->addColumn('order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('parent_id', Types::INTEGER, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['context_id', 'technical_name'], 'tables_ctx_menu_name_uq');

		return $schema;
	}
}
