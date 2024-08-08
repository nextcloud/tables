<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use JsonSerializable;

class Permissions implements JsonSerializable {
	public function __construct(
		public bool $read = false,
		public bool $create = false,
		public bool $update = false,
		public bool $delete = false,
		public bool $manage = false,
		public bool $manageTable = false,
	) {
	}

	/**
	 * @return array{read: bool, create: bool, update: bool, delete: bool, manage: bool}
	 */
	public function jsonSerialize(): array {
		// manageTable is not serialized as it is used in the backend only
		return [
			'read' => $this->read,
			'create' => $this->create,
			'update' => $this->update,
			'delete' => $this->delete,
			'manage' => $this->manage,
		];
	}
}
