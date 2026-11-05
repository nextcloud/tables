<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\BackgroundJob;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class BackfillRowUuids extends TimedJob {

	private const BATCH_SIZE = 500;
	private const TIME_BUDGET = 5;
	private const SQLITE_BATCH_PAUSE_MICROSECONDS = 10_000;
	private const CURSOR_KEY = 'row_uuid_backfill_cursor';

	public function __construct(
		ITimeFactory $time,
		private readonly IDBConnection $connection,
		private readonly IAppConfig $config,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(60);
		$this->setAllowParallelRuns(false);
	}

	public function run(mixed $argument): void {
		$deadline = $this->time->getTime() + self::TIME_BUDGET;
		$cursor = $this->config->getValueInt(Application::APP_ID, self::CURSOR_KEY, 0, true);
		$select = $this->connection->getQueryBuilder();
		$select->select('id')->from('tables_row_sleeves')
			->where($select->expr()->isNull('uuid'))
			->andWhere($select->expr()->gt('id', $select->createParameter('cursor')))
			->orderBy('id', 'ASC')->setMaxResults(self::BATCH_SIZE);
		$update = $this->connection->getQueryBuilder();
		$update->update('tables_row_sleeves')->set('uuid', $update->createParameter('uuid'))
			->where($update->expr()->eq('id', $update->createParameter('id')))
			->andWhere($update->expr()->isNull('uuid'));

		try {
			do {
				$select->setParameter('cursor', $cursor, IQueryBuilder::PARAM_INT);
				$result = $select->executeQuery();
				$ids = array_map('intval', $result->fetchFirstColumn());
				$result->closeCursor();
				if ($ids === []) {
					if ($cursor === 0) {
						$this->jobList->remove(self::class);
						$this->config->deleteKey(Application::APP_ID, self::CURSOR_KEY);
						return;
					}
					$cursor = 0;
					continue;
				}

				$cursor = $this->processBatch($ids, $update, $deadline);
				if ($this->connection->getDatabaseProvider() === IDBConnection::PLATFORM_SQLITE && $this->time->getTime() < $deadline) {
					// Yield SQLite's single writer between committed batches.
					usleep(self::SQLITE_BATCH_PAUSE_MICROSECONDS);
				}
			} while ($this->time->getTime() < $deadline);
			// A lagging cursor only repeats a NULL scan, so one write per run spares the shared config cache.
			$this->config->setValueInt(Application::APP_ID, self::CURSOR_KEY, $cursor, true);
		} catch (\Throwable $e) {
			$this->logger->error('Row UUID backfill will retry on its next run.', ['exception' => $e]);
		}
	}

	/** @param non-empty-list<int> $ids */
	private function processBatch(array $ids, IQueryBuilder $update, int $deadline): int {
		for ($attempt = 0; ; $attempt++) {
			$this->connection->beginTransaction();
			try {
				foreach ($ids as $id) {
					$update->setParameter('id', $id, IQueryBuilder::PARAM_INT);
					$update->setParameter('uuid', Uuid::v7()->toRfc4122());
					$update->executeStatement();
					if ($this->time->getTime() >= $deadline) {
						break;
					}
				}
				$this->connection->commit();
				return $id;
			} catch (\Throwable $e) {
				$this->connection->rollBack();
				// Only uuid changes in this statement, so any unique violation is a UUID collision.
				if (!$e instanceof Exception || $e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION || $attempt >= 3) {
					throw $e;
				}
			}
		}
	}
}
