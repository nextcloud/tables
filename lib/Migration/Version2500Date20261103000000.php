<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCA\Tables\BackgroundJob\BackfillRowUuids;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2500Date20261103000000 extends SimpleMigrationStep {
	public function __construct(
		private readonly IJobList $jobList,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('tables_row_sleeves');
		if (!$table->hasColumn('uuid')) {
			$table->addColumn('uuid', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 36,
			]);
		}
		if (!$table->hasIndex('tables_row_uuid_uniq')) {
			$table->addUniqueIndex(['uuid'], 'tables_row_uuid_uniq');
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->jobList->add(BackfillRowUuids::class);
	}
}
