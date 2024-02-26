<?php

declare(strict_types=1);

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method getContextId(): int
 * @method setContextId(int $value): void
 * @method getNodeId(): int
 * @method setNodeId(int $value): void
 * @method getNodeType(): string
 * @method setNodeType(string $value): void
 * @method getPermissions(): int
 * @method setPermissions(int $value): void
 */

class ContextNodeRelation extends Entity implements \JsonSerializable {
	protected ?int $contextId = null;
	protected ?int $nodeId = null;
	protected ?string $nodeType = null;
	protected ?int $permissions = null;

	public function __construct() {
		$this->addType('id', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contextId' => $this->getContextId(),
			'nodeId' => $this->getNodeId(),
			'nodeType' => $this->getNodeType(),
			'permissions' => $this->getPermissions()
		];
	}
}
