<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Dto;

readonly class RelationSettings {
	public function __construct(
		public string $relationType,
		public int $targetId,
		public int $labelColumn,
	) {
	}

	public static function fromArray(array $data): self {
		return new self(
			relationType: $data['relationType'],
			targetId: (int)$data['targetId'],
			labelColumn: (int)$data['labelColumn'],
		);
	}

	public function isView(): bool {
		return $this->relationType === 'view';
	}
}
