<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Listener;

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
		$rowId = $row->rowId;

		$this->auditLogService->log("Row with ID: $rowId was deleted", [
			'row' => $row->jsonSerialize(),
		]);
	}
}
