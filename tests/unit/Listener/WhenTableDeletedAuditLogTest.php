<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace unit\Listener;

use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Db\Table;
use OCA\Tables\Event\TableDeletedEvent;
use OCA\Tables\Listener\WhenTableDeletedAuditLogListener;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WhenTableDeletedAuditLogTest extends TestCase {
	private WhenTableDeletedAuditLogListener $listener;
	private MockObject $auditLogService;

	protected function setUp(): void {
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$this->listener = new WhenTableDeletedAuditLogListener($this->auditLogService);
	}

	public function testHandle(): void {
		$table = new Table();
		$table->id = 1;
		$table->setOwnership('alice');

		$event = new TableDeletedEvent($table);

		$this->auditLogService
			->expects($this->once())
			->method('log')
			->with(
				Operation::TableDeleted,
				'Table with ID: %s was deleted',
				['tableId' => 1, 'owner' => 'alice'],
			);

		$this->listener->handle($event);
	}
}
