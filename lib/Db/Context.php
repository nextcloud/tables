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
 *
 * @method getSharing(): array
 * @method setSharing(array $value): void
 * @method getNodes(): array
 * @method setNodes(array $value): void
 * @method getPages(): array
 * @method setPages(array $value): void
 */
class Context extends Entity implements JsonSerializable {
	protected ?string $name = null;
	protected ?string $icon = null;
	protected ?string $description = null;
	protected ?string $ownerId = null;
	protected ?int $ownerType = null;
	protected ?array $sharing = null;
	protected ?array $nodes = null;
	protected ?array $pages = null;

	public function __construct() {
		$this->addType('id', 'integer');
	}

	public function jsonSerialize(): array {
		// basic information
		$data = [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'iconName' => $this->getIcon(),
			'description' => $this->getDescription(),
			'owner' => $this->getOwnerId(),
			'ownerType' => $this->getOwnerType()
		];

		// extended data
		if (is_array($this->sharing) || is_array($this->nodes) || is_array($this->pages)) {
			$data['sharing'] = $this->getSharing();
			$data['nodes'] = $this->getNodes();
			$data['pages'] = $this->getPages();
		}

		return $data;
	}
}
