<?php

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellNumber> */
class RowCellNumber extends RowCellSuper {
	protected ?float $value = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
