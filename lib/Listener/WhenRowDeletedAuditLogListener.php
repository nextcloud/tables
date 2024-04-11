<?php

declare(strict_types=1);

namespace OCA\Tables\Listener;

use OCA\Tables\Event\RowDeletedEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|RowDeletedEvent>
 */
final class WhenRowDeletedAuditLogListener implements IEventListener {
	public function __construct(protected AuditLogServiceInterface $auditLogService) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof RowDeletedEvent)) {
			return;
		}

		$row = $event->getRow();
		$rowId = $row->getId();

		$this->auditLogService->log("Row with ID: $rowId was deleted", [
			'row' => $row->jsonSerialize(),
		]);
	}
}
