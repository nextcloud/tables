<?php

/** @noinspection PhpUnused */

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000200Date20220428000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = 'tables_shares';
		if (!$schema->hasTable($table)) {
			$table = $schema->createTable($table);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('sender', Types::STRING, [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('receiver', Types::STRING, [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('receiver_type', Types::STRING, [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('node_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('node_type', Types::STRING, [
				'notnull' => true,
				'length' => 50
			]);

			$table->addColumn('permission_read', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('permission_create', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('permission_update', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('permission_delete', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('permission_manage', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);

			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('last_edit_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}
}
