<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Dto;

readonly class RelationLookupSettings {
	public function __construct(
		public int $relationColumnId,
		public int $targetColumnId,
	) {
	}

	public static function fromArray(array $data): self {
		return new self(
			relationColumnId: (int)$data['relationColumnId'],
			targetColumnId: (int)$data['targetColumnId'],
		);
	}
}
