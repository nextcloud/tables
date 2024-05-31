<?php

namespace OCA\Tables\Db;

/** @template-extends RowCellSuper<RowCellUsergroup> */
class RowCellUsergroup extends RowCellSuper {
	protected ?string $value = null;
	protected ?int $valueType = null;

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value, $this->valueType);
	}
}
