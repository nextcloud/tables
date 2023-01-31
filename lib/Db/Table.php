<?php

namespace OCA\Tables\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Table extends Entity implements JsonSerializable {
	protected $title;

	protected $emoji;
	protected $ownership;
	protected $ownerDisplayName;
	protected $createdBy;
	protected $createdAt;
	protected $lastEditBy;
	protected $lastEditAt;
	protected $isShared;
	protected $onSharePermissions;

	public function __construct() {
		$this->addType('id', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'emoji' => $this->emoji,
			'ownership' => $this->ownership,
			'ownerDisplayName' => $this->ownerDisplayName,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
			'isShared' => !!$this->isShared,
			'onSharePermissions' => $this->onSharePermissions,
		];
	}
}
