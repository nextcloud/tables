<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
			$table->addColumn('id', Types::INTEGER, ['notnull' => true]);
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
			$table->addColumn('id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('context_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('node_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('node_type', Types::STRING, ['notnull' => true, 'length' => 50]);
			$table->addColumn('permissions', Types::INTEGER, ['notnull' => true]);

			$table->setPrimaryKey(['id']);
		}
	}

	/**
	 * @throws SchemaException
	 */
	protected function havePageTable(ISchemaWrapper $schema): void {
		if ($table = $this->shouldAddTable(self::PREFIX . 'page', $schema)) {
			$table->addColumn('id', Types::INTEGER, ['notnull' => true]);
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
			$table->addColumn('id', Types::INTEGER, ['notnull' => true]);
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
