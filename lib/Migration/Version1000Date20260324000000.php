<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version1000Date20260324000000 extends SimpleMigrationStep {
	private IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->update('tables_shares')
			->set('permission_read', $qb->createNamedParameter(0))
			->set('permission_create', $qb->createNamedParameter(0))
			->set('permission_update', $qb->createNamedParameter(0))
			->set('permission_delete', $qb->createNamedParameter(0))
			->set('permission_manage', $qb->createNamedParameter(0))
			->where($qb->expr()->eq('node_type', $qb->createNamedParameter('context')));
		$affected = $qb->executeStatement();
		$output->info('Version001100Date20260121180000: Rows affected: ' . $affected);
	}
}
