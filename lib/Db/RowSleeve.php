<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\Vendor\Symfony\Component\Uid\Uuid;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 * @method getUuid(): ?string
 * @method setUuid(?string $uuid)
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
	protected ?string $uuid = null;
	protected ?int $tableId = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('uuid', 'string');
		$this->addType('tableId', 'integer');
	}

	public function setter(string $name, array $args): void {
		if ($name === 'uuid') {
			$this->setUuidOnce($args[0]);
			return;
		}
		parent::setter($name, $args);
	}

	/**
	 * A row sleeve loaded from the database before the backfill has run has no
	 * uuid yet, so null is accepted while the uuid is unset.
	 */
	private function setUuidOnce(?string $uuid): void {
		if ($uuid === $this->uuid) {
			return;
		}
		if ($this->uuid !== null) {
			throw new \RuntimeException('This row already has a UUID, they are immutable');
		}
		$this->uuid = $uuid;
		$this->markFieldUpdated('uuid');
	}

	public function assignUuidIfMissing(): void {
		if ($this->uuid === null) {
			$this->setUuidOnce(Uuid::v7()->toRfc4122());
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
