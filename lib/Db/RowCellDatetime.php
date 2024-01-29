<?php

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellDatetime> */
class RowCellDatetime extends RowCellSuper {
	protected ?string $value = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
