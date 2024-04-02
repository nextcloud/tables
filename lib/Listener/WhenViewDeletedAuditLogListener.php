<?php

declare(strict_types=1);

namespace OCA\Tables\Listener;

use OCA\Tables\Event\ViewDeletedEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|ViewDeletedEvent>
 */
final class WhenViewDeletedAuditLogListener implements IEventListener {
	public function __construct(protected AuditLogServiceInterface $auditLogService) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof ViewDeletedEvent)) {
			return;
		}

		$view = $event->getView();
		$userId = $event->getUserId();

		$this->auditLogService->log("View with ID: $view->id was deleted by user with ID: $userId", [
			'view' => $view->jsonSerialize(),
			'userId' => $userId,
		]);
	}
}
