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

/**
 * A menu item may sit under another one, one level deep.
 */
class Version2400Date20261012030000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('tables_contexts_menu_item')) {
			$table = $schema->getTable('tables_contexts_menu_item');
			if (!$table->hasColumn('parent_id')) {
				$table->addColumn('parent_id', Types::INTEGER, [
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		return $schema;
	}
}
