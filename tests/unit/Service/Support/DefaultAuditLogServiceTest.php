<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace unit\Service\Support;

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
		$message = 'Test message';
		$context = ['key' => 'value'];

		$this->eventDispatcher
			->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event) use ($message, $context) {
				return $event->getLogMessage() === $message && $event->getParameters() === $context;
			}));

		$this->service->log($message, $context);
	}
}
