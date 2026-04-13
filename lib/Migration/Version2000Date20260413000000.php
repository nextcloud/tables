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

/**
 * Creates the tables_airtable_imports table used to track background import jobs.
 */
class Version2000Date20260413000000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$tableName = 'tables_airtable_imports';
		if ($schema->hasTable($tableName)) {
			return null;
		}

		$table = $schema->createTable($tableName);

		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('status', Types::STRING, [
			'notnull' => true,
			'length' => 32,
			'default' => 'pending',
		]);
		$table->addColumn('share_url', Types::TEXT, [
			'notnull' => true,
		]);
		$table->addColumn('target_context_id', Types::INTEGER, [
			'notnull' => false,
			'default' => null,
		]);
		$table->addColumn('progress_total', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('progress_done', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);
		$table->addColumn('error_message', Types::TEXT, [
			'notnull' => false,
			'default' => null,
		]);
		$table->addColumn('created_at', Types::DATETIME, [
			'notnull' => true,
		]);
		$table->addColumn('updated_at', Types::DATETIME, [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['user_id'], 'airtable_imports_user_idx');
		$table->addIndex(['status'], 'airtable_imports_status_idx');

		return $schema;
	}
}
