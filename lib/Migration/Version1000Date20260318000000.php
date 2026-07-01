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
use Override;

class Version1000Date20260318000000 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$tableName = 'tables_views';
		if (!$schema->hasTable($tableName)) {
			return null;
		}

		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('layout')) {
			$table->addColumn('layout', Types::STRING, [
				'notnull' => false,
				'length' => 16,
			]);
		}
		if (!$table->hasColumn('view_settings')) {
			$table->addColumn('view_settings', \Doctrine\DBAL\Types\Types::JSON, [
				'notnull' => false,
			]);
		}

		return $schema;
	}
}
