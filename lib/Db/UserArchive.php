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
 * @method isArchived(): bool
 */
class UserArchive extends EntitySuper {
	protected ?string $userId = null;
	protected ?int $nodeType = null;
	protected ?int $nodeId = null;
	protected bool $archived = true;

	public function __construct() {
		// Keyed by property, not column: Entity::$_fieldTypes is read with the
		// camelCase name, so a snake_case key here would silently do nothing.
		$this->addType('id', 'integer');
		$this->addType('nodeType', 'integer');
		$this->addType('nodeId', 'integer');
		$this->addType('archived', 'boolean');
	}
}
