<?php

declare(strict_types=1);

namespace OCA\Tables\Listener;

use OCA\Tables\Event\RowDeletedEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class WhenRowDeletedAuditLogListener implements IEventListener
{
    public function __construct(protected AuditLogServiceInterface $auditLogService)
    {
    }

    public function handle(Event $event): void
    {
        if (!($event instanceof RowDeletedEvent)) {
            return;
        }

        $row = $event->getRow();
        $userId = $event->getUserId();
        $rowId = $row->getId();

        $this->auditLogService->log("Row with ID: $rowId was deleted by user with ID: $userId", [
            'row' => $row->jsonSerialize(),
            'userId' => $userId,
        ]);
    }
}