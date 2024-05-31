<?php

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellNumber> */
class RowCellNumber extends RowCellSuper {
	protected ?float $value = null;
	protected ?int $valueType = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value, $this->valueType);
	}
}
