<?php

declare(strict_types=1);

namespace OCA\Tables\Listener;

use OCA\Tables\Event\TableDeletedEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|TableDeletedEvent>
 */
final class WhenTableDeletedAuditLogListener implements IEventListener {
	public function __construct(protected AuditLogServiceInterface $auditLogService) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof TableDeletedEvent)) {
			return;
		}

		$table = $event->getTable();

		$this->auditLogService->log("Table with ID: $table->id was deleted", [
			'table' => $table->jsonSerialize(),
		]);
	}
}
