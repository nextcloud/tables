<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use JsonSerializable;

/**
 * Points to the CSV problems file stored in the user's files app.
 *
 * Serialized to a plain array shape when passed through HTTP responses,
 * activity subject parameters or notification parameters:
 * array{id: int, name: string, path: string}
 */
final class ImportProblemsFile implements JsonSerializable {
	public function __construct(
		public int $id,
		public string $name,
		public string $path,
	) {
	}

	/**
	 * @return array{id: int, name: string, path: string}
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'path' => $this->path,
		];
	}
}
