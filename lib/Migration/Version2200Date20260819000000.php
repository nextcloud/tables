<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Migration;

use Closure;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version2200Date20260819000000 extends SimpleMigrationStep {
	private IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('tables_tables');
		if (!$table->hasColumn('uuid')) {
			$table->addColumn('uuid', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 36,
				'comment' => 'UUIDv7 identifier to support structural updates across instances',
			]);
		}
		if (!$table->hasIndex('tables_tables_uuid_uniq')) {
			$table->addUniqueIndex(['uuid'], 'tables_tables_uuid_uniq');
		}

		return $schema;
	}

	#[Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Generate UUIDs for existing rows in the tables_tables table
		$qb = $this->connection->getQueryBuilder();
		$qb->update('tables_tables')
			->set('uuid', $qb->createParameter('uuid'))
			->where($qb->expr()->eq('id', $qb->createParameter('tableLocalId')));

		$qbSelect = $this->connection->getQueryBuilder();
		$qbSelect->select('id')
			->from('tables_tables');
		$select = $qbSelect->executeQuery();

		$writeBatches = 250;
		$updates = 0;

		try {
			$this->connection->beginTransaction();
			while (($row = $select->fetchAssociative()) !== false) {
				$qb->setParameter('tableLocalId', $row['id']);
				$qb->setParameter('uuid', Uuid::v7()->toRfc4122());
				$qb->executeStatement();

				$updates++;
				if ($updates % $writeBatches === 0) {
					$this->connection->commit();
					$this->connection->beginTransaction();
				}
			}
			$this->connection->commit();
		} catch (\Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}
	}
}
