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

class Version2210Date20260709000000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tables_views')) {
			return null;
		}

		$table = $schema->getTable('tables_views');
		if (!$table->hasColumn('sidebar_order')) {
			$table->addColumn('sidebar_order', Types::BIGINT, [
				'notnull' => false,
				'default' => null,
			]);
		}

		return $schema;
	}
}
