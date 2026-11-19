<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Tables\Db\Context;
use OCA\Tables\Db\ContextMapper;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\PermissionError;
use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UserDeletedListenerTest extends TestCase {
	private TableService&MockObject $tableService;
	private ContextService&MockObject $contextService;
	private ContextMapper&MockObject $contextMapper;
	private LoggerInterface&MockObject $logger;
	private UserDeletedListener $listener;

	protected function setUp(): void {
		$this->tableService = $this->createMock(TableService::class);
		$this->contextService = $this->createMock(ContextService::class);
		$this->contextMapper = $this->createMock(ContextMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new UserDeletedListener(
			$this->tableService,
			$this->contextService,
			$this->contextMapper,
			$this->logger,
		);
	}

	public function testDeletesTheApplicationsAndTablesTheUserOwns(): void {
		$this->contextMapper->expects($this->once())->method('findIdsOwnedByUser')->with('alice')->willReturn([1, 4]);
		$this->tableService->expects($this->once())->method('findAll')->with('alice', true, true, false)->willReturn([$this->table(7)]);

		$deleted = [];
		$this->contextService->expects($this->exactly(2))->method('delete')
			->willReturnCallback(function (int $contextId, string $userId) use (&$deleted): Context {
				$deleted[] = [$contextId, $userId];
				return new Context();
			});
		$this->tableService->expects($this->once())->method('delete')->with(7, 'alice');
		$this->logger->expects($this->never())->method('error');

		$this->listener->handle($this->event('alice'));

		$this->assertSame([[1, 'alice'], [4, 'alice']], $deleted);
	}

	public function testDeletesApplicationsBeforeTables(): void {
		$calls = [];
		$this->contextMapper->method('findIdsOwnedByUser')->willReturn([1]);
		$this->contextService->method('delete')->willReturnCallback(function () use (&$calls): Context {
			$calls[] = 'application';
			return new Context();
		});
		$table = $this->table(7);
		$this->tableService->method('findAll')->willReturn([$table]);
		$this->tableService->method('delete')->with(7, 'alice')->willReturnCallback(function () use (&$calls, $table): Table {
			$calls[] = 'table';
			return $table;
		});

		$this->listener->handle($this->event('alice'));

		$this->assertSame(['application', 'table'], $calls);
	}

	public function testAFailingApplicationDoesNotStopTheRest(): void {
		$this->contextMapper->method('findIdsOwnedByUser')->willReturn([1, 2]);
		$this->contextService->expects($this->exactly(2))->method('delete')
			->willReturnCallback(function (int $contextId): Context {
				if ($contextId === 1) {
					throw new \RuntimeException('broken');
				}
				return new Context();
			});
		$this->tableService->method('findAll')->willReturn([$this->table(7)]);
		$this->tableService->expects($this->once())->method('delete')->with(7, 'alice');
		$this->logger->expects($this->once())->method('error')
			->with($this->stringContains('could not delete application 1'));

		$this->listener->handle($this->event('alice'));
	}

	public function testFailingToFindTheApplicationsStillDeletesTheTables(): void {
		$this->contextMapper->method('findIdsOwnedByUser')->willThrowException(new Exception('db down'));
		$this->contextService->expects($this->never())->method('delete');
		$this->tableService->method('findAll')->willReturn([$this->table(7)]);
		$this->tableService->expects($this->once())->method('delete')->with(7, 'alice');
		$this->logger->expects($this->once())->method('error')
			->with($this->stringContains('could not find the applications'));

		$this->listener->handle($this->event('alice'));
	}

	public function testAFailingTableDoesNotStopTheRest(): void {
		$this->contextMapper->method('findIdsOwnedByUser')->willReturn([]);
		$this->tableService->method('findAll')->willReturn([$this->table(7), $this->table(8)]);
		$deleted = [];
		$this->tableService->expects($this->exactly(2))->method('delete')
			->willReturnCallback(function (int $tableId) use (&$deleted): Table {
				if ($tableId === 7) {
					throw new PermissionError('no');
				}
				$deleted[] = $tableId;
				return $this->table($tableId);
			});
		$this->logger->expects($this->once())->method('error')
			->with($this->stringContains('could not delete table 7'));

		$this->listener->handle($this->event('alice'));

		$this->assertSame([8], $deleted);
	}

	public function testFailingToFindTheTablesIsLogged(): void {
		$this->contextMapper->method('findIdsOwnedByUser')->willReturn([]);
		$this->tableService->method('findAll')->willThrowException(new Exception('db down'));
		$this->tableService->expects($this->never())->method('delete');
		$this->logger->expects($this->once())->method('error')
			->with($this->stringContains('could not find the tables'));

		$this->listener->handle($this->event('alice'));
	}

	public function testIgnoresTheEventBeforeTheDeletion(): void {
		$this->contextMapper->expects($this->never())->method('findIdsOwnedByUser');
		$this->tableService->expects($this->never())->method('findAll');

		$this->listener->handle(new BeforeUserDeletedEvent($this->user('alice')));
	}

	public function testIgnoresOtherEvents(): void {
		$this->contextMapper->expects($this->never())->method('findIdsOwnedByUser');
		$this->tableService->expects($this->never())->method('findAll');

		$this->listener->handle(new Event());
	}

	private function table(int $id): Table {
		$table = new Table();
		$table->setId($id);
		return $table;
	}

	private function user(string $userId): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$user->method('getDisplayName')->willReturn($userId);
		return $user;
	}

	private function event(string $userId): UserDeletedEvent {
		return new UserDeletedEvent($this->user($userId));
	}
}
