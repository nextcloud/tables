<?php

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class LogItem extends Entity implements JsonSerializable {
	protected $userId;
	protected $time;
	protected $actionType;
	protected $actionData;
	protected $triggerType;
	protected $dataType;

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
