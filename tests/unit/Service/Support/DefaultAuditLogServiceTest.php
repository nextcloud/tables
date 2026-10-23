<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace unit\Service\Support;

use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Service\Support\DefaultAuditLogService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DefaultAuditLogServiceTest extends TestCase {
	private DefaultAuditLogService $service;
	private MockObject $eventDispatcher;

	protected function setUp(): void {
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);

		$this->service = new DefaultAuditLogService($this->eventDispatcher);
	}

	public function testLog(): void {
		$message = 'Table with ID: %s was deleted';
		$parameters = ['tableId' => 1];

		$this->eventDispatcher
			->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event) => $event->getLogMessage() === $message
				&& $event->getParameters() === $parameters
				&& (!method_exists($event, 'getOperation') || $event->getOperation() === 'tables.table.deleted')));

		$this->service->log(Operation::TableDeleted, $message, $parameters);
	}
}
