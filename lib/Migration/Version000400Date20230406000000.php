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
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000400Date20230406000000 extends SimpleMigrationStep {
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

		$table = 'tables_columns';
		if ($schema->hasTable($table)) {
			$table = $schema->getTable($table);

			$column = $table->getColumn('selection_options');
			$column->setLength(65535);
			$column->setType(Type::getType('text'));

			$column = $table->getColumn('selection_default');
			$column->setLength(65535);
			$column->setType(Type::getType('text'));

			$column = $table->getColumn('text_default');
			$column->setLength(65535);
			$column->setType(Type::getType('text'));
		}

		return $schema;
	}
}
