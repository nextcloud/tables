<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000800Date20240213123743 extends SimpleMigrationStep {

	protected const PREFIX = 'tables_contexts_';

	/**
	 * @throws SchemaException
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// Introduction of Contexts tables

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->haveContextTable($schema);
		$this->haveContextNodeRelationTable($schema);
		$this->havePageTable($schema);
		$this->havePageContentTable($schema);
		$this->haveNavigationTable($schema);

		return $schema;
	}

	protected function shouldAddTable(string $tableName, ISchemaWrapper $schema): ?Table {
		return !$schema->hasTable($tableName) ? $schema->createTable($tableName) : null;
	}

	/**
	 * @throws SchemaException
	 */
	protected function haveContextTable(ISchemaWrapper $schema): void {
		if ($table = $this->shouldAddTable(self::PREFIX . 'context', $schema)) {
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 200]);
			$table->addColumn('icon', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('description', Types::TEXT);
			$table->addColumn('owner_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('owner_type', Types::INTEGER, ['notnull' => true]);

			$table->setPrimaryKey(['id']);
		}
	}

	/**
	 * @throws SchemaException
	 */
	protected function haveContextNodeRelationTable(ISchemaWrapper $schema): void {
		if ($table = $this->shouldAddTable(self::PREFIX . 'rel_context_node', $schema)) {
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('context_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('node_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('node_type', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('permissions', Types::INTEGER, ['notnull' => true]);

			$table->setPrimaryKey(['id']);
		}
	}

	/**
	 * @throws SchemaException
	 */
	protected function havePageTable(ISchemaWrapper $schema): void {
		if ($table = $this->shouldAddTable(self::PREFIX . 'page', $schema)) {
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('context_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('page_type', Types::STRING, ['notnull' => true, 'length' => 32]);

			$table->setPrimaryKey(['id']);
		}
	}

	/**
	 * @throws SchemaException
	 */
	protected function havePageContentTable(ISchemaWrapper $schema): void {
		if ($table = $this->shouldAddTable(self::PREFIX . 'page_content', $schema)) {
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('page_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('node_rel_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('order', Types::INTEGER, ['notnull' => true]);

			$table->setPrimaryKey(['id']);
		}
	}

	/**
	 * @throws SchemaException
	 */
	protected function haveNavigationTable(ISchemaWrapper $schema): void {
		if ($table = $this->shouldAddTable(self::PREFIX . 'navigation', $schema)) {
			$table->addColumn('share_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('display_mode', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => '']);

			$table->setPrimaryKey(['share_id', 'user_id']);
		}
	}
}
