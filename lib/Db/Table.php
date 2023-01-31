<?php

namespace OCA\Tables\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Table extends Entity implements JsonSerializable {
	protected ?string $title = null;

	protected ?string $emoji = null;
	protected ?string $ownership = null;
	protected ?string $ownerDisplayName = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;
	protected ?bool $isShared = null;
	protected ?array $onSharePermissions = null;

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
