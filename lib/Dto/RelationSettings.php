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
		public string $targetSetBy = '',
	) {
		if (!in_array($relationType, ['table', 'view'], true)) {
			throw new \InvalidArgumentException('Relation type must be table or view.');
		}
		if ($targetId <= 0 || $labelColumn <= 0) {
			throw new \InvalidArgumentException('Relation target and label column IDs must be positive integers.');
		}
	}

	public static function fromArray(array $data): self {
		if (!isset($data['relationType']) || !is_string($data['relationType'])) {
			throw new \InvalidArgumentException('Relation type is required.');
		}
		if (isset($data['targetSetBy']) && !is_string($data['targetSetBy'])) {
			throw new \InvalidArgumentException('Relation target author must be a string.');
		}
		return new self(
			relationType: $data['relationType'],
			targetId: self::parseIntField($data['targetId'] ?? null, 'targetId'),
			labelColumn: self::parseIntField($data['labelColumn'] ?? null, 'labelColumn'),
			targetSetBy: $data['targetSetBy'] ?? '',
		);
	}

	/**
	 * Settings may arrive as form parameters, where integers are encoded as strings.
	 */
	private static function parseIntField(mixed $value, string $field): int {
		if (is_string($value) && ctype_digit($value)) {
			$value = (int)$value;
		}
		if (!is_int($value)) {
			throw new \InvalidArgumentException($field . ' must be an integer.');
		}
		return $value;
	}

	public function isView(): bool {
		return $this->relationType === 'view';
	}
}
