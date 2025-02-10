<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Listener;

use OCA\Tables\Event\ViewDeletedEvent;
use OCA\Tables\Service\Support\AuditLogServiceInterface;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event|ViewDeletedEvent>
 */
final class WhenViewDeletedAuditLogListener implements IEventListener {
	public function __construct(
		protected AuditLogServiceInterface $auditLogService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof ViewDeletedEvent)) {
			return;
		}

		$view = $event->getView();

		$this->auditLogService->log("View with ID: $view->id was deleted", [
			'view' => $view->jsonSerialize(),
		]);
	}
}
