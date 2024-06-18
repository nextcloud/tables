<?php

namespace OCA\Tables\Model;

class Permissions {
	public function __construct(
		public bool $read = false,
		public bool $create = false,
		public bool $update = false,
		public bool $delete = false,
		public bool $manage = false,
		public bool $manageTable = false,
	) {
	}
}
