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

class Version2200Date20260425000000 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->addFormattingColumnToViews($schema);
		$this->createFormattingRuleColsTable($schema);

		return $schema;
	}

	private function addFormattingColumnToViews(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('tables_views')) {
			return;
		}

		$table = $schema->getTable('tables_views');

		if (!$table->hasColumn('formatting')) {
			$table->addColumn('formatting', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
		}
	}

	private function createFormattingRuleColsTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('tables_fmt_rule_cols')) {
			return;
		}

		$table = $schema->createTable('tables_fmt_rule_cols');
		$table->addColumn('rule_id', Types::STRING, [
			'length' => 36,
			'notnull' => true,
		]);
		$table->addColumn('view_id', Types::BIGINT, [
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('column_id', Types::BIGINT, [
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->setPrimaryKey(['rule_id', 'column_id']);
		$table->addIndex(['column_id'], 'fmt_rulecols_col');
		$table->addIndex(['view_id'], 'fmt_rulecols_view');
	}
}
