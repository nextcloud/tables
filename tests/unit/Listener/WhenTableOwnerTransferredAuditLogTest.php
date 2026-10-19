<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace unit\Listener;

use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Db\Table;
use OCA\Tables\Event\TableOwnershipTransferredEvent;
use OCA\Tables\Listener\WhenTableTransferredAuditLogListener;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WhenTableOwnerTransferredAuditLogTest extends TestCase {
	private WhenTableTransferredAuditLogListener $listener;
	private MockObject $auditLogService;

	protected function setUp(): void {
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$this->listener = new WhenTableTransferredAuditLogListener($this->auditLogService);
	}

	public function testHandle(): void {
		$table = new Table();
		$table->id = 1;
		$fromUserId = 'user1';
		$toUserId = 'user2';

		$event = new TableOwnershipTransferredEvent($table, $toUserId, $fromUserId);

		$this->auditLogService
			->expects($this->once())
			->method('log')
			->with(
				Operation::TableTransferred,
				'Table with ID: %s was transferred from user with ID: %s to user with ID: %s',
				['tableId' => 1, 'fromUserId' => $fromUserId, 'toUserId' => $toUserId],
			);

		$this->listener->handle($event);
	}
}
