<?php

declare(strict_types=1);

namespace OCA\Tables\Event;

use OCA\Tables\Db\Table;
use OCP\EventDispatcher\Event;

final class TableDeletedEvent extends Event {
	public function __construct(protected Table $table) {
		parent::__construct();
	}

	public function getTable(): Table {
		return $this->table;
	}
}
