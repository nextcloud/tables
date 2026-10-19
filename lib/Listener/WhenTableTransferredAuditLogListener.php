<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Event\TableOwnershipTransferredEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|TableOwnershipTransferredEvent>
 */
final class WhenTableTransferredAuditLogListener implements IEventListener {
	public function __construct(
		protected AuditLogServiceInterface $auditLogService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof TableOwnershipTransferredEvent)) {
			return;
		}

		$this->auditLogService->log(Operation::TableTransferred, 'Table with ID: %s was transferred from user with ID: %s to user with ID: %s', [
			'tableId' => $event->getTable()->getId(),
			'fromUserId' => $event->getFromUserId(),
			'toUserId' => $event->getToUserId(),
		]);
	}
}
