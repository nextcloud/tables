<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

class RowCellRelation extends RowCellSuper {
	protected ?string $value = null;

	public function __construct() {
		parent::__construct();
		$this->addType('value', 'string');
	}

	public function jsonSerialize(): array {
		return parent::jsonSerializePreparation($this->value);
	}
}
