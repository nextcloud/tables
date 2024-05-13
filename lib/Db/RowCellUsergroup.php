<?php

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellUsergroup> */
class RowCellUsergroup extends RowCellSuper {
	protected ?string $value = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
