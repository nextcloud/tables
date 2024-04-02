<?php

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
		$userId = 'user1';

		$event = new ViewDeletedEvent($view, $userId);

		$this->auditLogService
			->expects($this->once())
			->method('log')
			->with(
				$this->equalTo("View with ID: {$view->id} was deleted by user with ID: $userId"),
				$this->equalTo([
					'view' => $view->jsonSerialize(),
					'userId' => $userId,
				])
			);

		$this->listener->handle($event);
	}
}
