<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

/**
 * @template-extends RowCellSuper<RowCellUsergroup>
 * @method setValueType(int $param)
 * @method getValueType(): int
 */
class RowCellUsergroup extends RowCellSuper {
	protected ?string $value = null;
	protected ?int $valueType = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value, $this->valueType);
	}

	public function setValueWrapper($value) {
		$this->setValue((string)$value['id']);
		$this->setValueType((int)$value['type']);
	}

	public static function verifyUserGroupArray(array $data): bool {
		if (!array_key_exists('id', $data)) {
			return false;
		}
		if (!array_key_exists('type', $data)) {
			return false;
		}

		return true;
	}
}
