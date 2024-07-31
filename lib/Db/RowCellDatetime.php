<?php

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellDatetime> */
class RowCellDatetime extends RowCellSuper {
	protected ?string $value = null;
	protected ?int $valueType = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value, $this->valueType);
	}
}
