<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\ShareReview;

/**
 * Typed container for a single share entry returned by ShareReviewSource.
 *
 * @psalm-type ShareInfoArray = array{
 *     id: int,
 *     object: string,
 *     initiator: string,
 *     type: int,
 *     recipient: string,
 *     permissions: int,
 *     password: bool,
 *     time: string,
 *     action: string,
 * }
 */
class ShareInfo {
	public function __construct(
		public readonly int $id,
		public readonly string $object,
		public readonly string $initiator,
		public readonly int $type,
		public readonly string $recipient,
		public readonly int $permissions,
		public readonly bool $password,
		public readonly string $time,
	) {
	}

	/**
	 * @return array{id: int, object: string, initiator: string, type: int, recipient: string, permissions: int, password: bool, time: string, action: string}
	 */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'object' => $this->object,
			'initiator' => $this->initiator,
			'type' => $this->type,
			'recipient' => $this->recipient,
			'permissions' => $this->permissions,
			'password' => $this->password,
			'time' => $this->time,
			'action' => '',
		];
	}
}
