<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace unit\Listener;

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
				$this->equalTo("Table with ID: {$table->id} was transferred from user with ID: $fromUserId to user with ID: $toUserId"),
				$this->equalTo([
					'table' => $table->jsonSerialize(),
					'fromUserId' => $fromUserId,
					'toUserId' => $toUserId,
				])
			);

		$this->listener->handle($event);
	}
}
