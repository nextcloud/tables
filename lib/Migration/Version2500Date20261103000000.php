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
 * The menu of an application. An item opens a page, a table or view of the
 * application, or a link; an item that opens nothing is a group holding other items.
 */
class Version2500Date20261103000000 extends SimpleMigrationStep {
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
		$table->addColumn('parent_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('title', Types::STRING, ['notnull' => false, 'length' => 200]);
		$table->addColumn('icon', Types::STRING, ['notnull' => false, 'length' => 64]);
		$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		$table->addColumn('page_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('node_rel_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('url', Types::STRING, ['notnull' => false, 'length' => 2000]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['context_id'], 'tables_ctx_menu_ctx_idx');
		$table->addIndex(['page_id'], 'tables_ctx_menu_page_idx');
		$table->addIndex(['node_rel_id'], 'tables_ctx_menu_rel_idx');

		return $schema;
	}
}
