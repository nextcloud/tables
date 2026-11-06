<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Listener;

use OCA\Tables\AppInfo\Application;
use OCA\Tables\Db\Context;
use OCA\Tables\Db\Table;
use OCA\Tables\Errors\NotFoundError;
use OCA\Tables\Listener\UserDeletedListener;
use OCA\Tables\Service\ContextService;
use OCA\Tables\Service\TableService;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\BeforeUserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UserDeletedListenerTest extends TestCase {
	private TableService&MockObject $tableService;
	private ContextService&MockObject $contextService;
	private UserDeletedListener $listener;

	protected function setUp(): void {
		$this->tableService = $this->createMock(TableService::class);
		$this->contextService = $this->createMock(ContextService::class);

		$this->listener = new UserDeletedListener(
			$this->tableService,
			$this->contextService,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testDeletesOnlyTheApplicationsTheUserOwns(): void {
		$this->contextService->method('findAll')->with('alice')->willReturn([
			$this->context(1, 'alice', Application::OWNER_TYPE_USER),
			$this->context(2, 'bob', Application::OWNER_TYPE_USER),
			$this->context(3, 'alice', 1),
			$this->context(4, 'alice', Application::OWNER_TYPE_USER),
		]);
		$this->tableService->method('findAll')->willReturn([]);

		$deleted = [];
		$this->contextService->expects($this->exactly(2))->method('delete')
			->willReturnCallback(function (int $contextId, string $userId) use (&$deleted): Context {
				$deleted[] = [$contextId, $userId];
				return new Context();
			});

		$this->listener->handle($this->event('alice'));

		$this->assertSame([[1, 'alice'], [4, 'alice']], $deleted);
	}

	public function testDeletesApplicationsBeforeTables(): void {
		$calls = [];
		$this->contextService->method('findAll')->willReturn([$this->context(1, 'alice', Application::OWNER_TYPE_USER)]);
		$this->contextService->method('delete')->willReturnCallback(function () use (&$calls): Context {
			$calls[] = 'application';
			return new Context();
		});
		$table = new Table();
		$table->setId(7);
		$this->tableService->method('findAll')->with('alice', true, true, false)->willReturn([$table]);
		$this->tableService->method('delete')->with(7, 'alice')->willReturnCallback(function () use (&$calls, $table): Table {
			$calls[] = 'table';
			return $table;
		});

		$this->listener->handle($this->event('alice'));

		$this->assertSame(['application', 'table'], $calls);
	}

	public function testAFailingApplicationDoesNotStopTheRest(): void {
		$this->contextService->method('findAll')->willReturn([
			$this->context(1, 'alice', Application::OWNER_TYPE_USER),
			$this->context(2, 'alice', Application::OWNER_TYPE_USER),
		]);
		$this->contextService->expects($this->exactly(2))->method('delete')
			->willReturnCallback(function (int $contextId): Context {
				if ($contextId === 1) {
					throw new NotFoundError('gone');
				}
				return new Context();
			});
		$table = new Table();
		$table->setId(7);
		$this->tableService->method('findAll')->willReturn([$table]);
		$this->tableService->expects($this->once())->method('delete')->with(7, 'alice');

		$this->listener->handle($this->event('alice'));
	}

	public function testIgnoresOtherEvents(): void {
		$this->contextService->expects($this->never())->method('findAll');
		$this->tableService->expects($this->never())->method('findAll');

		$this->listener->handle(new Event());
	}

	private function context(int $id, string $ownerId, int $ownerType): Context {
		$context = new Context();
		$context->setId($id);
		$context->setOwnerId($ownerId);
		$context->setOwnerType($ownerType);
		return $context;
	}

	private function event(string $userId): BeforeUserDeletedEvent {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$user->method('getDisplayName')->willReturn($userId);
		return new BeforeUserDeletedEvent($user);
	}
}
