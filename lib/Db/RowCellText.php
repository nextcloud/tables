<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellText> */
class RowCellText extends RowCellSuper {
	protected ?string $value = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
