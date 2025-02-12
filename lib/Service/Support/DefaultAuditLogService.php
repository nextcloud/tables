<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Service\Support;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

final class DefaultAuditLogService implements AuditLogServiceInterface {
	public function __construct(
		private IEventDispatcher $eventDispatcher,
	) {
	}

	public function log(string $message, array $context): void {
		$auditEvent = new CriticalActionPerformedEvent($message, $context);

		$this->eventDispatcher->dispatchTyped($auditEvent);
	}
}
