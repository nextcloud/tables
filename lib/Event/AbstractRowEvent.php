<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Event;

use OCA\Tables\Db\Row2;
use OCA\Tables\Model\Public\Row;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

if (interface_exists(\OCP\EventDispatcher\IWebhookCompatibleEvent::class)) {
	abstract class AbstractRowEvent extends Event implements IWebhookCompatibleEvent {
		protected Row $row;

		public function __construct(Row2 $rowEntity, ?array $previousValues = null) {
			parent::__construct();
			$this->row = $rowEntity->toPublicRow($previousValues);
		}

		public function getRow(): Row {
			return $this->row;
		}

		public function getWebhookSerializable(): array {
			return $this->row->jsonSerialize();
		}
	}
} else {
	// need this block as long as NC < 30 is supported
	abstract class AbstractRowEvent extends Event {
		protected Row $row;

		public function __construct(Row2 $rowEntity, ?array $previousValues = null) {
			parent::__construct();
			$this->row = $rowEntity->toPublicRow($previousValues);
		}

		public function getRow(): Row {
			return $this->row;
		}
	}
}
