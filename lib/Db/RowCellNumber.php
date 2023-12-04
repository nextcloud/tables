<?php

namespace OCA\Tables\Db;

class RowCellNumber extends RowCellSuper {
	protected ?float $value = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
