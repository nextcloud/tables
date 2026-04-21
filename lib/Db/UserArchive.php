<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

/**
 * @method getUserId(): string
 * @method setUserId(string $value): void
 * @method getNodeType(): int
 * @method setNodeType(int $value): void
 * @method getNodeId(): int
 * @method setNodeId(int $value): void
 * @method setArchived(bool $value): void
 */
class UserArchive extends EntitySuper {
	protected ?string $userId = null;
	protected ?int $nodeType = null;
	protected ?int $nodeId = null;
	protected bool $archived = true;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('node_type', 'integer');
		$this->addType('node_id', 'integer');
		$this->addType('archived', 'boolean');
	}

	public function isArchived(): bool {
		return $this->archived;
	}
}
