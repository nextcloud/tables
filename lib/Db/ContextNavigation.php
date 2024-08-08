<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method getShareId(): int
 * @method setShareId(int $value): void
 * @method getUserId(): string
 * @method setUserId(string $value): void
 * @method getDisplayMode(): int
 * @method setDisplayMode(int $value): void
 */
class ContextNavigation extends Entity implements \JsonSerializable {
	protected ?int $shareId = null;
	protected ?string $userId = null;
	protected ?int $displayMode = null;

	public function __construct() {
		$this->addType('shareId', 'integer');
		$this->addType('displayMode', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'shareId' => $this->getShareId(),
			'displayMode' => $this->getDisplayMode(),
			'userId' => $this->getUserId(),
		];
	}
}
