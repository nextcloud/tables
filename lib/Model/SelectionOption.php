<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

class SelectionOption implements \JsonSerializable {

	public function __construct(
		private readonly int $key,
		private readonly string $label,
	) {
	}

	public static function createFromInputArray(array $data): self {
		if (!isset($data['id']) || !is_numeric($data['id'])) {
			throw new \InvalidArgumentException('Only integer keys are allowed for options');
		}

		if (!isset($data['label'])) {
			throw new \InvalidArgumentException('Option label is missing');
		}

		if (isset($data['uuid'])) {
			throw new \InvalidArgumentException('It is forbidden to set the Uuid from external');
		}

		return new self((int)$data['id'], $data['label']);
	}

	public function key(): int {
		return $this->key;
	}

	public function label(): string {
		return $this->label;
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => $this->key,
			'label' => $this->label,
		];
	}
}
