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

class Version2010Date20260414000000 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tables_tables')) {
			return null;
		}

		$tablesTables = $schema->getTable('tables_tables');

		if (!$tablesTables->hasColumn('column_order')) {
			$tablesTables->addColumn('column_order', Types::TEXT, [
				'notnull' => false,
				'default' => null,
				'comment' => 'JSON array of ViewColumnInformation — default column order for the table',
			]);
		}

		if (!$tablesTables->hasColumn('sort')) {
			$tablesTables->addColumn('sort', Types::TEXT, [
				'notnull' => false,
				'default' => null,
				'comment' => 'JSON array of sort rules — default row sort for the table',
			]);
		}

		return $schema;
	}
}
