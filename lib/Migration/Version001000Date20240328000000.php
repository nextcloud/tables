<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use Doctrine\DBAL\Exception;
use OCP\IDBConnection;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20240328000000 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
	) {
	}

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

		if ($schema->hasTable('tables_views')) {
			$table = $schema->getTable('tables_views');

			// Add new column_settings field
			if (!$table->hasColumn('column_settings')) {
				$table->addColumn('column_settings', Types::JSON, [
					'notnull' => false,
					'comment' => 'JSON structure for column-specific settings like order, visibility, etc.',
				]);
			}

			return $schema;
		}

		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('tables_views')) {
			// Get all views
			$qb = $this->connection->getQueryBuilder();
			$qb->select('id', 'columns')
				->from('tables_views')
				->where($qb->expr()->isNotNull('columns'));

			$result = $qb->executeQuery();
			$views = $result->fetchAll();

			// Update each view
			foreach ($views as $view) {
				if (empty($view['columns'])) {
					continue;
				}

				// Parse existing columns JSON
				$columns = json_decode($view['columns'], true);
				if (!is_array($columns)) {
					continue;
				}

				// Create new column_settings structure
				$columnSettings = [];
				foreach ($columns as $order => $columnId) {
					$columnSettings[] = [
						'columnId' => (int)$columnId,
						'order' => $order,
					];
				}

				// Update the view with new column_settings
				$updateQb = $this->connection->getQueryBuilder();
				$updateQb->update('tables_views')
					->set('column_settings', $updateQb->createNamedParameter(json_encode($columnSettings)))
					->where($updateQb->expr()->eq('id', $updateQb->createNamedParameter($view['id'], \PDO::PARAM_INT)));
				$updateQb->executeStatement();
			}

			$result->closeCursor();
		}
	}
}
