<?php

declare(strict_types=1);

namespace OCA\Tables\Listener;

use OCA\Tables\Event\TableOwnershipTransferredEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|TableOwnershipTransferredEvent>
 */
final class WhenTableTransferredAuditLogListener implements IEventListener {
	public function __construct(protected AuditLogServiceInterface $auditLogService) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof TableOwnershipTransferredEvent)) {
			return;
		}

		$table = $event->getTable();
		$fromUserId = $event->getFromUserId();
		$toUserId = $event->getToUserId();

		$this->auditLogService->log("Table with ID: $table->id was transferred from user with ID: $fromUserId to user with ID: $toUserId", [
			'table' => $table->jsonSerialize(),
			'fromUserId' => $fromUserId,
			'toUserId' => $toUserId,
		]);
	}
}
