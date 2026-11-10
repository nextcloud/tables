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
use Override;

/**
 * Adds a uuid to rows. Existing rows get theirs from a background job, so the column
 * is nullable.
 */
class Version2500Date20261014000000 extends SimpleMigrationStep {
	private const TABLE = 'tables_row_sleeves';
	private const COL_UUID = 'uuid';
	private const INDEX_UUID = 'tables_rows_uuid_uniq';

	public function __construct(
		private readonly IJobList $jobList,
	) {
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable(self::TABLE)) {
			return $schema;
		}

		$table = $schema->getTable(self::TABLE);
		if (!$table->hasColumn(self::COL_UUID)) {
			$table->addColumn(self::COL_UUID, Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 36,
				'comment' => 'UUIDv7 identifier of the row, unique across all tables',
			]);
		}
		if (!$table->hasIndex(self::INDEX_UUID)) {
			$table->addUniqueIndex([self::COL_UUID], self::INDEX_UUID);
		}

		return $schema;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->jobList->add(BackfillRowUuids::class);
	}
}
