<?php

/** @noinspection PhpUnused */

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000900Date20250408000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('tables_row_sleeves')) {
			$table = $schema->getTable('tables_row_sleeves');

			// reverting "$table->addIndex(['id'])" done in Version000700Date20230916000000 since redundant
			// as the id column is already the primary key and thus indexed
			foreach ($table->getIndexes() as $index) {
				if (!$index->isPrimary() && $index->getColumns() === ['id']) {
					$table->dropIndex($index->getName());
				}
			}
		}

		return $schema;
	}
}
