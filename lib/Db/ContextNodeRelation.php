<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method getContextId(): int
 * @method setContextId(int $value): void
 * @method getNodeId(): int
 * @method setNodeId(int $value): void
 * @method getNodeType(): int
 * @method setNodeType(int $value): void
 * @method getPermissions(): int
 * @method setPermissions(int $value): void
 */

class ContextNodeRelation extends Entity implements \JsonSerializable {
	protected ?int $contextId = null;
	protected ?int $nodeId = null;
	protected ?int $nodeType = null;
	protected ?int $permissions = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('context_id', 'integer');
		$this->addType('node_id', 'integer');
		$this->addType('node_type', 'integer');
		$this->addType('permissions', 'integer');
	}

	/**
	 * The relation as an application carries it among its nodes, in the shape the
	 * context mapper reads it.
	 *
	 * @return array{id: int, node_id: int, node_type: int, permissions: int}
	 */
	public function toContextNode(): array {
		return [
			'id' => (int)$this->getId(),
			'node_id' => (int)$this->getNodeId(),
			'node_type' => (int)$this->getNodeType(),
			'permissions' => (int)$this->getPermissions(),
		];
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
