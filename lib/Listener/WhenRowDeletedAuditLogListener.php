<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Listener;

use OCA\Tables\AuditLog\Operation;
use OCA\Tables\Event\RowDeletedEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|RowDeletedEvent>
 */
final class WhenRowDeletedAuditLogListener implements IEventListener {
	public function __construct(
		protected AuditLogServiceInterface $auditLogService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof RowDeletedEvent)) {
			return;
		}

		$row = $event->getRow();

		$this->auditLogService->log(Operation::RowDeleted, 'Row with ID: %s was deleted', [
			'rowId' => $row->rowId,
			'tableId' => $row->tableId,
		]);
	}
}
