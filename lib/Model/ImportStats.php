<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

final class ImportStats {
	public function __construct(
		public int $foundColumnsCount,
		public int $matchingColumnsCount,
		public int $createdColumnsCount,
		public int $insertedRowsCount,
		public int $updatedRowsCount,
		public int $errorsParsingCount,
		public int $errorsCount,
	) {
	}
}
