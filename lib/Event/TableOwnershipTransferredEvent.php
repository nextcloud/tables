<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Event;

use OCA\Tables\Db\Table;
use OCP\EventDispatcher\Event;

final class TableOwnershipTransferredEvent extends Event {
	public function __construct(
		protected Table $table,
		protected string $toUserId,
		protected ?string $fromUserId = null,
	) {
		parent::__construct();
	}

	public function getTable(): Table {
		return $this->table;
	}

	public function getFromUserId(): ?string {
		return $this->fromUserId;
	}

	public function getToUserId(): string {
		return $this->toUserId;
	}
}
