<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ValueObject;

/**
 * Extends ColumnOrderInformation with view-specific fields (readonly, mandatory).
 */
class ViewColumnInformation extends ColumnOrderInformation {
	public const KEY_READONLY = 'readonly';
	public const KEY_MANDATORY = 'mandatory';

	protected const KEYS = [
		self::KEY_ID,
		self::KEY_ORDER,
		self::KEY_READONLY,
		self::KEY_MANDATORY,
	];

	public function __construct(
		int $columnId,
		int $order,
		bool $readonly = false,
		bool $mandatory = false,
	) {
		parent::__construct($columnId, $order);
		$this->offsetSet(self::KEY_READONLY, $readonly);
		$this->offsetSet(self::KEY_MANDATORY, $mandatory);
	}

	public function isReadonly(): bool {
		return (bool)$this->offsetGet(self::KEY_READONLY);
	}

	public function isMandatory(): bool {
		return (bool)$this->offsetGet(self::KEY_MANDATORY);
	}

	public static function fromArray(array $data): static {
		static::assertRequiredFields($data);
		return new static(
			(int)$data[self::KEY_ID],
			(int)$data[self::KEY_ORDER],
			(bool)($data[self::KEY_READONLY] ?? false),
			(bool)($data[self::KEY_MANDATORY] ?? false),
		);
	}

	/**
	 * @return array{columnId: int, order: int, readonly: bool, mandatory: bool}
	 */
	public function jsonSerialize(): array {
		return array_merge(parent::jsonSerialize(), [
			self::KEY_READONLY => $this->isReadonly(),
			self::KEY_MANDATORY => $this->isMandatory(),
		]);
	}

	protected function ensureType(string $offset, mixed $value): int|bool {
		return match ($offset) {
			self::KEY_READONLY => (bool)$value,
			self::KEY_MANDATORY => (bool)$value,
			default => parent::ensureType($offset, $value),
		};
	}
}
