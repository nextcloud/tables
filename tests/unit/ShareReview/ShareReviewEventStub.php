<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Share\Events;

/**
 * Runtime stub for OCP\Share\Events\ShareReviewAccessCheckEvent.
 * Only loaded when the server does not provide the class.
 */
class ShareReviewAccessCheckEvent extends \OCP\EventDispatcher\Event {
	private bool $handled = false;
	private bool $granted = false;

	public function __construct(string $appId, string $shareId) {
	}

	public function grantAccess(): void {
		$this->handled = true;
		$this->granted = true;
	}

	public function denyAccess(string $reason): void {
		$this->handled = true;
	}

	public function isHandled(): bool {
		return $this->handled;
	}

	public function isGranted(): bool {
		return $this->granted;
	}
}
