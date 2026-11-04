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
 * A widget can sit in a tab of a tabs widget on the same page: `parent_id` is the tabs
 * widget and `parent_tab` the position of the tab, both empty for a widget on the page itself.
 */
class Version2500Date20261030010000 extends SimpleMigrationStep {
	private const WIDGET_TABLE = 'tables_contexts_widget';

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable(self::WIDGET_TABLE)) {
			return null;
		}
		$table = $schema->getTable(self::WIDGET_TABLE);
		if ($table->hasColumn('parent_id')) {
			return null;
		}
		$table->addColumn('parent_id', Types::INTEGER, ['notnull' => false]);
		$table->addColumn('parent_tab', Types::SMALLINT, ['notnull' => false]);
		return $schema;
	}
}
