<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\IDBConnection;

/**
 * Trait that helps mappers to avoid errors with too many parameters
 */
trait BulkFetchTrait {
	private function getChunkSize(int $extraParameters = 0): int {
		$maxParameters = match ($this->db->getDatabaseProvider()) {
			IDBConnection::PLATFORM_ORACLE => 1000,
			default => 65_535,
		};
		return $maxParameters - $extraParameters;
	}
}
