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
 * @method int|null getTableId()
 * @method setTableId(int $columnId)
 * @method string getCreatedBy()
 * @method setCreatedBy(string $createdBy)
 * @method string getCreatedAt()
 * @method setCreatedAt(string $createdAt)
 * @method string getLastEditBy()
 * @method setLastEditBy(string $lastEditBy)
 * @method string getLastEditAt()
 * @method setLastEditAt(string $lastEditAt)
 */
class RowSleeve extends Entity implements JsonSerializable {
	protected ?int $tableId = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'tableId' => $this->tableId,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
		];
	}
}
