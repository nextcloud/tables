<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LogItem extends Entity implements JsonSerializable {
	protected ?string $userId = null;
	protected ?string $time = null;
	protected ?string $actionType = null;
	protected ?string $actionData = null;
	protected ?string $triggerType = null;
	protected ?string $dataType = null;

	public function __construct() {
		$this->addType('id', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'time' => $this->time,
			'actionType' => $this->actionType,
			'actionData' => $this->actionData,
			'triggerType' => $this->triggerType,
			'dataType' => $this->dataType,
		];
	}
}
