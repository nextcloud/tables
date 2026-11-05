<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCA\Tables\Vendor\Symfony\Component\Uid\UuidV7;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 * @method getUuid(): ?string
 * @method getTableId(): ?int
 * @method setTableId(int $columnId)
 * @method getCreatedBy(): string
 * @method setCreatedBy(string $createdBy)
 * @method getCreatedAt(): string
 * @method setCreatedAt(string $createdAt)
 * @method getLastEditBy(): string
 * @method setLastEditBy(string $lastEditBy)
 * @method getLastEditAt(): string
 * @method setLastEditAt(string $lastEditAt)
 */
class RowSleeve extends Entity implements JsonSerializable {
	protected ?int $tableId = null;
	protected ?string $uuid = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
		$this->addType('uuid', 'string');
	}

	/**
	 * Hydration uses Entity::setter() instead, so stored values load unchanged.
	 */
	public function setUuid(?string $uuid): void {
		$uuid = $uuid !== null ? (new UuidV7($uuid))->toRfc4122() : null;
		if ($this->uuid !== null && $uuid !== $this->uuid) {
			throw new \InvalidArgumentException('Row UUIDs are immutable.');
		}
		$this->uuid = $uuid;
		$this->markFieldUpdated('uuid');
	}

	public function assignUuid(): void {
		if ($this->uuid === null) {
			$this->setUuid(Uuid::v7()->toRfc4122());
		}
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'uuid' => $this->uuid,
			'tableId' => $this->tableId,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
		];
	}
}
