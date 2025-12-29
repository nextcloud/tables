<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 * @method getTableId(): ?int
 * @method setTableId(int $columnId)
 * @method getCachedCells(): string
 * @method setCachedCells(string $cachedCells)
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
	protected ?string $cachedCells = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
		$this->addType('cachedCells', 'string');
	}

	/**
	 * @return array<int, mixed> Indexed by column ID
	 */
	public function getCachedCellsArray(): array {
		return json_decode($this->cachedCells, true) ?: [];
	}

	public function setCachedCellsArray(array $array):void {
		$this->setCachedCells(json_encode($array));
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'tableId' => $this->tableId,
			'cachedCells' => $this->cachedCells,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
		];
	}
}
