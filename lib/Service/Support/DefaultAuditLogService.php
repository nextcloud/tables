<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\Support;

use OCA\Tables\AuditLog\Operation;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

final class DefaultAuditLogService implements AuditLogServiceInterface {
	public function __construct(
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * The operation is only passed on to servers whose CriticalActionPerformedEvent supports it
	 */
	public function log(Operation $operation, string $message, array $parameters = []): void {
		$arguments = [$message, $parameters, false];
		if (method_exists(CriticalActionPerformedEvent::class, 'getOperation')) {
			$arguments[] = $operation->value;
		}

		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(...$arguments));
	}
}
