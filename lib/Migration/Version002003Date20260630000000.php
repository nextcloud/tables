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

class Version002003Date20260630000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('tables_tables');
		if (!$table->hasColumn('external_id')) {
			$table->addColumn('external_id', Types::INTEGER, ['notnull' => false]);
		}
		if (!$table->hasColumn('share_token')) {
			$table->addColumn('share_token', Types::STRING, ['notnull' => false, 'length' => 64]);
		}

		$view = $schema->getTable('tables_views');
		if (!$view->hasColumn('external_id')) {
			$view->addColumn('external_id', Types::INTEGER, ['notnull' => false]);
		}
		if (!$view->hasColumn('share_token')) {
			$view->addColumn('share_token', Types::STRING, ['notnull' => false, 'length' => 64]);
		}
		if ($view->getColumn('table_id')->getNotnull()) {
			$view->modifyColumn('table_id', ['notnull' => false]);
		}

		return $schema;
	}
}
