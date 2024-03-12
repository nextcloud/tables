<?php

namespace OCA\Tables\Controller;

use OCA\Tables\Db\Table;

class AEnvironmentAwareOCSController extends AOCSController {
	protected ?Table $table;

	public function setTable(Table $table): void {
		$this->table = $table;
	}

	public function getTable(): ?Table {
		return $this->table;
	}
}
