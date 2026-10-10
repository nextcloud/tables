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

class Version2301Date20260930000000 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tables_contexts_context')) {
			return null;
		}

		$contexts = $schema->getTable('tables_contexts_context');

		if (!$contexts->hasColumn('card_view_enabled')) {
			$contexts->addColumn('card_view_enabled', Types::BOOLEAN, [
				'notnull' => false,
				'default' => false,
				'comment' => 'Show the application resources as cards instead of stacked',
			]);
		}

		return $schema;
	}
}
