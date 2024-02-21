<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method getName(): string
 * @method setName(string $value): void
 * @method getIcon(): string
 * @method setIcon(string $value): void
 * @method getDescription(): string
 * @method setDescription(string $value): void
 * @method getOwnerId(): string
 * @method setOwnerId(string $value): void
 * @method getOwnerType(): int
 * @method setOwnerType(int $value): void
 */
class Context extends Entity implements JsonSerializable {
	protected ?string $name = null;
	protected ?string $icon = null;
	protected ?string $description = null;
	protected ?string $ownerId = null;
	protected ?int $ownerType = null;

	public function __construct() {
		$this->addType('id', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'iconName' => $this->getIcon(),
			'description' => $this->getDescription(),
			'owner' => $this->getOwnerId(),
			'ownerType' => $this->getOwnerType()
		];
	}
}
