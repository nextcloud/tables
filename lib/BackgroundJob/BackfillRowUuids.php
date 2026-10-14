<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Override;

/**
 * Gives existing rows a uuid in batches, so an upgrade does not lock large tables.
 * The job queues itself again as long as rows without a uuid may remain.
 */
class BackfillRowUuids extends QueuedJob {
	public const BATCH_SIZE = 1_000;
	public const BATCHES_PER_RUN = 10;

	private const TABLE = 'tables_row_sleeves';

	public function __construct(
		ITimeFactory $time,
		private readonly IDBConnection $connection,
		private readonly IJobList $jobList,
	) {
		parent::__construct($time);
	}

	#[Override]
	protected function run($argument): void {
		try {
			$isComplete = $this->backfill();
		} catch (\Throwable $e) {
			$this->jobList->add(self::class);
			throw $e;
		}
		if (!$isComplete) {
			$this->jobList->add(self::class);
		}
	}

	/**
	 * @return bool whether all rows have a uuid now
	 */
	private function backfill(): bool {
		$selectQuery = $this->connection->getQueryBuilder();
		$selectQuery->select('id')
			->from(self::TABLE)
			->where($selectQuery->expr()->isNull('uuid'))
			->orderBy('id', 'ASC')
			->setMaxResults(static::BATCH_SIZE);

		$updateQuery = $this->connection->getQueryBuilder();
		$updateQuery->update(self::TABLE)
			->set('uuid', $updateQuery->createParameter('uuid'))
			->where($updateQuery->expr()->eq('id', $updateQuery->createParameter('id')))
			->andWhere($updateQuery->expr()->isNull('uuid'));

		for ($batch = 0; $batch < static::BATCHES_PER_RUN; $batch++) {
			$result = $selectQuery->executeQuery();
			$rowIds = [];
			while (($rowId = $result->fetchOne()) !== false) {
				$rowIds[] = (int)$rowId;
			}
			$result->closeCursor();

			$this->assignUuids($updateQuery, $rowIds);
			if (count($rowIds) < static::BATCH_SIZE) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param int[] $rowIds
	 */
	private function assignUuids(IQueryBuilder $updateQuery, array $rowIds): void {
		if ($rowIds === []) {
			return;
		}
		$this->connection->beginTransaction();
		try {
			foreach ($rowIds as $rowId) {
				$updateQuery->setParameter('uuid', Uuid::v7()->toRfc4122(), IQueryBuilder::PARAM_STR);
				$updateQuery->setParameter('id', $rowId, IQueryBuilder::PARAM_INT);
				$updateQuery->executeStatement();
			}
			$this->connection->commit();
		} catch (\Throwable $e) {
			$this->connection->rollBack();
			throw $e;
		}
	}
}
