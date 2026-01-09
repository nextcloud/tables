<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellRelation> */
class RowCellRelation extends RowCellSuper {
	protected ?int $value = null;

	public function __construct() {
		parent::__construct();
		$this->addType('value', 'integer');
	}

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
