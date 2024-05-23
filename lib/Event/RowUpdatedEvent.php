<?php

declare(strict_types=1);

namespace OCA\Tables\Event;

use OCA\Tables\Db\Row2;
use OCP\EventDispatcher\Event;

final class RowUpdatedEvent extends Event {
	public function __construct(protected Row2 $row) {
		parent::__construct();
	}

	public function getRow(): Row2 {
		return $this->row;
	}
}
