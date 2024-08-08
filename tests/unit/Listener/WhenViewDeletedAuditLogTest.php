<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace unit\Listener;

use OCA\Tables\Db\View;
use OCA\Tables\Event\ViewDeletedEvent;
use OCA\Tables\Listener\WhenViewDeletedAuditLogListener;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WhenViewDeletedAuditLogTest extends TestCase {
	private WhenViewDeletedAuditLogListener $listener;
	private MockObject $auditLogService;

	protected function setUp(): void {
		$this->auditLogService = $this->createMock(AuditLogServiceInterface::class);

		$this->listener = new WhenViewDeletedAuditLogListener($this->auditLogService);
	}

	public function testHandle(): void {
		$view = new View();
		$view->id = 1;

		$event = new ViewDeletedEvent(view: $view);

		$this->auditLogService
			->expects($this->once())
			->method('log')
			->with(
				$this->equalTo("View with ID: {$view->id} was deleted"),
				$this->equalTo([
					'view' => $view->jsonSerialize(),
				])
			);

		$this->listener->handle($event);
	}
}
