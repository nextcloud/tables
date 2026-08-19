<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;

class SelectionOption implements \JsonSerializable {
	private string $uuid;

	public function __construct(
		private readonly int $key,
		private readonly string $label,
	) {
	}

	/**
	 * @psalm-param array{id: int|string, label: string, uuid?: string} $data
	 */
	public static function createFromInputArray(array $data, bool $allowPassingUuid = false): self {
		if (!isset($data['id']) || !is_numeric($data['id'])) {
			throw new \InvalidArgumentException('Only integer keys are allowed for options');
		}

		if (!isset($data['label'])) {
			throw new \InvalidArgumentException('Option label is missing');
		}

		$instance = new self((int)$data['id'], $data['label']);
		if ($allowPassingUuid && isset($data['uuid']) && is_string($data['uuid']) && $data['uuid'] !== '') {
			$instance->setUuid($data['uuid']);
		}
		return $instance;
	}

	public function key(): int {
		return $this->key;
	}

	public function label(): string {
		return $this->label;
	}

	public function uuid(): ?string {
		/** @psalm-suppress RedundantPropertyInitializationCheck */
		return $this->uuid ?? null;
	}

	/**
	 * UUID is assigned once, if there is no UUID a new one is generated, existing ones are never replaced
	 */
	public function ensureUuid(?string $existingUuid = null): void {
		if ($this->uuid() !== null) {
			return;
		}
		if ($existingUuid !== null && $existingUuid !== '') {
			$this->setUuid($existingUuid);
			return;
		}
		$this->setUuid(Uuid::v7()->toRfc4122());
	}

	protected function setUuid(string $uuid): void {
		if ($this->uuid() !== null) {
			return;
		}
		$this->uuid = $uuid;
	}

	#[\Override]
	public function jsonSerialize(): array {
		$out = [
			'id' => $this->key,
			'label' => $this->label,
		];
		/** @psalm-suppress RedundantPropertyInitializationCheck */
		if (isset($this->uuid)) {
			$out['uuid'] = $this->uuid;
		}
		return $out;
	}
}
