<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellNumber> */
class RowCellNumber extends RowCellSuper {
	protected ?float $value = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
