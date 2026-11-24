<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\BackgroundJob;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\BackgroundJob\BackfillRowUuids;
use OCA\Tables\Db\RowSleeveMapper;
use OCA\Tables\Tests\Unit\Database\DatabaseTestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class BackfillRowUuidsTest extends DatabaseTestCase {
	private array $settings;
	private IAppConfig $config;
	private IJobList $jobList;
	private RowSleeveMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		$this->connectionAdapter->beginTransaction();
		$delete = $this->connectionAdapter->getQueryBuilder();
		$delete->delete('tables_row_sleeves')->where($delete->expr()->gt('id', $delete->createNamedParameter(0)))->executeStatement();
		$this->settings = [];
		$this->config = $this->createMock(IAppConfig::class);
		$this->config->method('getValueInt')->willReturnCallback(fn ($app, $key, $default) => $this->settings[$key] ?? $default);
		$this->config->method('setValueInt')->willReturnCallback(function ($app, $key, $value): bool {
			$this->settings[$key] = $value;
			return true;
		});
		$this->config->method('deleteKey')->willReturnCallback(function ($app, $key): void {
			unset($this->settings[$key]);
		});
		$this->jobList = $this->createMock(IJobList::class);
		$this->mapper = new RowSleeveMapper($this->connectionAdapter, new NullLogger());
	}

	protected function tearDown(): void {
		$this->connectionAdapter->rollBack();
		parent::tearDown();
	}

	private function historicalRows(int $count): array {
		$insert = $this->connectionAdapter->getQueryBuilder();
		$insert->insert('tables_row_sleeves')->values([
			'table_id' => $insert->createNamedParameter(991),
			'created_by' => $insert->createNamedParameter('original-author'),
			'created_at' => $insert->createNamedParameter('2020-01-01 00:00:00'),
			'last_edit_by' => $insert->createNamedParameter('original-editor'),
			'last_edit_at' => $insert->createNamedParameter('2020-01-02 00:00:00'),
		]);
		$ids = [];
		for ($i = 0; $i < $count; $i++) {
			$insert->executeStatement();
			$ids[] = $insert->getLastInsertId();
		}
		return $ids;
	}

	private function job(ITimeFactory $clock, ?LoggerInterface $logger = null): BackfillRowUuids {
		return new BackfillRowUuids($clock, $this->connectionAdapter, $this->config, $this->jobList, $logger ?? new NullLogger());
	}

	public function testTimeBudgetCheckpointsProcessedPrefixAndResumePreservesMetadata(): void {
		$ids = $this->historicalRows(3);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturnOnConsecutiveCalls(0, 5, 5, 5);
		$this->jobList->expects($this->once())->method('remove')->with(BackfillRowUuids::class);
		$this->job($clock)->run(null);
		$this->assertSame($ids[0], $this->settings['row_uuid_backfill_cursor']);
		$uuid = $this->mapper->find($ids[0])->getUuid();
		$this->assertNotNull($uuid);
		$this->assertNull($this->mapper->find($ids[1])->getUuid());

		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(10);
		$this->job($clock)->run(null);
		$this->assertSame($uuid, $this->mapper->find($ids[0])->getUuid());
		foreach ($ids as $id) {
			$row = $this->mapper->find($id);
			$this->assertNotNull($row->getUuid());
			$this->assertSame('original-author', $row->getCreatedBy());
			$this->assertSame('original-editor', $row->getLastEditBy());
			$this->assertSame('2020-01-01 00:00:00', $row->getCreatedAt());
			$this->assertSame('2020-01-02 00:00:00', $row->getLastEditAt());
		}
		$this->assertSame([], $this->settings);
	}

	public function testFinalPassRevisitsNullsBelowCursor(): void {
		$ids = $this->historicalRows(2);
		$this->settings['row_uuid_backfill_cursor'] = $ids[1] + 100;
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(0);
		$this->jobList->expects($this->once())->method('remove');
		$this->job($clock)->run(null);
		$this->assertNotNull($this->mapper->find($ids[0])->getUuid());
		$this->assertNotNull($this->mapper->find($ids[1])->getUuid());
	}

	public function testCommittedWorkSurvivesCheckpointFailure(): void {
		$ids = $this->historicalRows(1);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')->willReturn(0);
		$config->method('setValueInt')->willThrowException(new \RuntimeException('Checkpoint unavailable'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturnOnConsecutiveCalls(0, 5, 5, 5);
		$job = new BackfillRowUuids($clock, $this->connectionAdapter, $config, $this->jobList, $logger);
		$job->run(null);
		$uuid = $this->mapper->find($ids[0])->getUuid();
		$this->assertNotNull($uuid);
		$resumeClock = $this->createMock(ITimeFactory::class);
		$resumeClock->method('getTime')->willReturn(10);
		$this->jobList->expects($this->once())->method('remove');
		$this->job($resumeClock)->run(null);
		$this->assertSame($uuid, $this->mapper->find($ids[0])->getUuid());
	}

	public function testCheckpointIsWrittenLazilyOncePerExecution(): void {
		$ids = $this->historicalRows(501);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')->with(Application::APP_ID, 'row_uuid_backfill_cursor', 0, true)->willReturn(0);
		$config->expects($this->once())->method('setValueInt')->with(Application::APP_ID, 'row_uuid_backfill_cursor', $ids[500], true)->willReturn(true);
		$calls = 0;
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturnCallback(function () use (&$calls): int {
			return ++$calls > 503 ? 5 : 0;
		});
		$job = new BackfillRowUuids($clock, $this->connectionAdapter, $config, $this->jobList, new NullLogger());
		$job->run(null);
		$this->assertNotNull($this->mapper->find($ids[500])->getUuid());
	}

	public function testFailedBatchRollsBackItsPrefixBeforeRetrying(): void {
		$ids = $this->historicalRows(2);
		$actualUpdate = $this->connectionAdapter->getQueryBuilder();
		$update = $this->createMock(IQueryBuilder::class);
		foreach (['update', 'set', 'where', 'andWhere', 'setParameter'] as $method) {
			$update->method($method)->willReturnCallback(function (...$arguments) use ($actualUpdate, $update, $method) {
				$actualUpdate->$method(...$arguments);
				return $update;
			});
		}
		$update->method('expr')->willReturn($actualUpdate->expr());
		$update->method('createParameter')->willReturnCallback(fn ($name) => $actualUpdate->createParameter($name));
		$calls = 0;
		$update->method('executeStatement')->willReturnCallback(function () use ($actualUpdate, &$calls): int {
			if (++$calls === 2) {
				throw new \RuntimeException('Database write interrupted');
			}
			return $actualUpdate->executeStatement();
		});
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getQueryBuilder')->willReturnOnConsecutiveCalls($this->connectionAdapter->getQueryBuilder(), $update);
		$connection->expects($this->once())->method('beginTransaction')->willReturnCallback(fn () => $this->connectionAdapter->beginTransaction());
		$connection->expects($this->once())->method('rollBack')->willReturnCallback(fn () => $this->connectionAdapter->rollBack());
		$connection->expects($this->never())->method('commit');
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(0);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('error');
		$job = new BackfillRowUuids($clock, $connection, $this->config, $this->jobList, $logger);
		$job->run(null);
		$this->assertSame([], $this->settings);
		$this->assertNull($this->mapper->find($ids[0])->getUuid());
		$this->assertNull($this->mapper->find($ids[1])->getUuid());
		$this->jobList->expects($this->once())->method('remove');
		$this->job($clock)->run(null);
		$this->assertNotNull($this->mapper->find($ids[0])->getUuid());
		$this->assertNotNull($this->mapper->find($ids[1])->getUuid());
	}

	public function testInterleavedAssignmentAndDeletionAreNotOverwritten(): void {
		$ids = $this->historicalRows(3);
		$uuid = '019f90c5-8576-7f0b-9b14-9bf49b2826cf';
		$assign = $this->connectionAdapter->getQueryBuilder();
		$assign->update('tables_row_sleeves')->set('uuid', $assign->createNamedParameter($uuid))
			->where($assign->expr()->eq('id', $assign->createNamedParameter($ids[1])));
		$delete = $this->connectionAdapter->getQueryBuilder();
		$delete->delete('tables_row_sleeves')->where($delete->expr()->eq('id', $delete->createNamedParameter($ids[2])));
		$clock = $this->createMock(ITimeFactory::class);
		$calls = 0;
		$clock->method('getTime')->willReturnCallback(function () use (&$calls, $assign, $delete): int {
			if (++$calls === 2) {
				$assign->executeStatement();
				$delete->executeStatement();
			}
			return 0;
		});
		$this->jobList->expects($this->once())->method('remove');
		$this->job($clock)->run(null);
		$this->assertNotNull($this->mapper->find($ids[0])->getUuid());
		$this->assertSame($uuid, $this->mapper->find($ids[1])->getUuid());
		$this->expectException(\OCP\AppFramework\Db\DoesNotExistException::class);
		$this->mapper->find($ids[2]);
	}

	public function testMoreThanOneBatchCompletesWithUniqueIdentities(): void {
		$ids = $this->historicalRows(501);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturn(0);
		$this->jobList->expects($this->once())->method('remove');
		$this->job($clock)->run(null);
		$uuids = array_map(fn ($id) => $this->mapper->find($id)->getUuid(), $ids);
		$this->assertNotContains(null, $uuids);
		$this->assertCount(501, array_unique($uuids));
	}
}
