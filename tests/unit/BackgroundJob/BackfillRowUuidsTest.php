<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\BackgroundJob;

use OCA\Tables\BackgroundJob\BackfillRowUuids;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;

class SmallBatchBackfillRowUuids extends BackfillRowUuids {
	public const BATCH_SIZE = 2;
	public const BATCHES_PER_RUN = 1;
}

class BackfillRowUuidsTest extends DatabaseTestCase {
	/** Other tests leave rows without a uuid behind, so the job may need more runs than this test's rows alone. */
	private const MAX_RUNS = 10_000;

	private int $tableId;
	private int $queuedAgain = 0;

	protected function setUp(): void {
		parent::setUp();
		$this->tableId = $this->createTestTable()['id'];
	}

	protected function tearDown(): void {
		foreach (['tables_row_sleeves' => 'table_id', 'tables_tables' => 'id'] as $table => $column) {
			$qb = $this->connection->getQueryBuilder();
			$qb->delete($table)
				->where($qb->expr()->eq($column, $qb->createNamedParameter($this->tableId)))
				->executeStatement();
		}
		parent::tearDown();
	}

	private function createJob(): BackfillRowUuids {
		$jobList = $this->createMock(IJobList::class);
		$jobList->method('add')->willReturnCallback(function (string $job): void {
			$this->assertSame(BackfillRowUuids::class, $job);
			$this->queuedAgain++;
		});
		return new SmallBatchBackfillRowUuids($this->createMock(ITimeFactory::class), $this->connectionAdapter, $jobList);
	}

	private function runJob(BackfillRowUuids $job): void {
		(new \ReflectionMethod($job, 'run'))->invoke($job, null);
	}

	private function uuidOf(int $rowId): ?string {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('uuid')
			->from('tables_row_sleeves')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($rowId)));
		$uuid = $qb->executeQuery()->fetchOne();
		return $uuid === false ? null : $uuid;
	}

	public function testGivesEveryRowAUuidAndKeepsExistingOnes(): void {
		$existingUuid = Uuid::v7()->toRfc4122();
		$rowWithUuid = $this->createTestRow($this->tableId, ['uuid' => $existingUuid])['id'];
		$rowsWithoutUuid = [];
		for ($i = 0; $i < 3; $i++) {
			$rowsWithoutUuid[] = $this->createTestRow($this->tableId)['id'];
		}

		$job = $this->createJob();
		$runs = 0;
		do {
			$queuedBefore = $this->queuedAgain;
			$this->runJob($job);
			$runs++;
		} while ($this->queuedAgain > $queuedBefore && $runs < self::MAX_RUNS);

		$this->assertGreaterThan(1, $runs, 'More rows than one batch should make the job queue itself again');
		$this->assertSame($runs - 1, $this->queuedAgain, 'The last run should not queue the job again');
		$this->assertSame($existingUuid, $this->uuidOf($rowWithUuid));
		$assignedUuids = array_map(fn (int $rowId): ?string => $this->uuidOf($rowId), $rowsWithoutUuid);
		foreach ($assignedUuids as $uuid) {
			$this->assertNotNull($uuid);
			$this->assertTrue(Uuid::isValid($uuid));
		}
		$this->assertCount(3, array_unique($assignedUuids));
	}

	public function testDoesNotQueueAgainWhenNothingIsLeft(): void {
		$job = $this->createJob();
		$runs = 0;
		do {
			$queuedBefore = $this->queuedAgain;
			$this->runJob($job);
			$runs++;
		} while ($this->queuedAgain > $queuedBefore && $runs < self::MAX_RUNS);

		$queuedBefore = $this->queuedAgain;
		$this->runJob($job);

		$this->assertSame($queuedBefore, $this->queuedAgain);
	}
}
