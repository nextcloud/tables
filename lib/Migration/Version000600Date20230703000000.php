<?php

/** @noinspection PhpUnused */

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Migration;

use Closure;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\SchemaException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000600Date20230703000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @throws SchemaException
	 * @throws Exception
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = 'tables_views';
		if (!$schema->hasTable($table)) {
			$table = $schema->createTable($table);
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('table_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('emoji', Types::STRING, [
				'notnull' => false,
				'length' => 20
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('last_edit_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('last_edit_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('columns', \Doctrine\DBAL\Types\Types::JSON, [
				'notnull' => false,
			]);
			$table->addColumn('sort', \Doctrine\DBAL\Types\Types::JSON, [
				'notnull' => false,
			]);
			$table->addColumn('filter', \Doctrine\DBAL\Types\Types::JSON, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}
}
