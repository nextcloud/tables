<?php

namespace unit\Listener;

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
		$userId = 'user1';

		$event = new TableDeletedEvent($table, $userId);

		$this->auditLogService
			->expects($this->once())
			->method('log')
			->with(
				$this->equalTo("Table with ID: {$table->id} was deleted by user with ID: $userId"),
				$this->equalTo([
					'table' => $table->jsonSerialize(),
					'userId' => $userId,
				])
			);

		$this->listener->handle($event);
	}
}
