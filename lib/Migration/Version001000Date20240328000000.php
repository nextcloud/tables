<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001000Date20240328000000 extends SimpleMigrationStep {
	public function __construct(
		protected IDBConnection $connection,
		protected IJobList $jobList,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Register background job to convert column format
		$this->jobList->add("OCA\Tables\BackgroundJob\ConvertViewColumnsFormat");
	}
}
