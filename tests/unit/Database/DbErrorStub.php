<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Tests\Unit\Database;

use OCP\DB\Exception;

/**
 * A database error with a reason, as the database layer throws it.
 */
class DbErrorStub extends Exception {
	public function __construct(
		private readonly int $reason,
	) {
		parent::__construct('database error');
	}

	public static function uniqueViolation(): self {
		return new self(Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION);
	}

	public function getReason(): ?int {
		return $this->reason;
	}
}
