<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version1000Date20251208192653 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$tableName = 'tables_shares';
		if (!$schema->hasTable($tableName)) {
			return null;
		}

		$table = $schema->getTable($tableName);
		if (!$table->hasColumn('token')) {
			$table->addColumn('token', Types::STRING, [
				'notnull' => false,
				'length' => 64
			]);
		}

		if (!$table->hasColumn('password')) {
			$table->addColumn('password', Types::STRING, [
				'notnull' => false,
				'length' => 255
			]);
		}

		if (!$table->hasIndex('shares_token_idx')) {
			$table->addIndex(['token'], 'shares_token_idx');
		}

		return $schema;
	}
}
