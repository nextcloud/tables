<?php

/** @noinspection PhpUnused */

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000800Date20240222000000 extends SimpleMigrationStep {
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
			$table->addColumn('archived', Types::BOOLEAN, [
				'default' => false,
				'notnull' => true,
			]);
		}

		if (!$schema->hasTable('tables_favorites')) {
			$table = $schema->createTable('tables_favorites');
			$table->addColumn('id', Types::BIGINT, [
				'notnull' => true,
				'autoincrement' => true,
				'unsigned' => true,
			]);
			$table->addColumn('node_type', Types::SMALLINT, [
				'notnull' => true,
			]);
			$table->addColumn('node_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'idx_tables_fav_uid');
		}

		return $schema;
	}

}
