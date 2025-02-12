<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 * @method getTableId(): int
 * @method setTableId(int $tableId)
 * @method getCreatedBy(): string
 * @method setCreatedBy(string $createdBy)
 * @method getCreatedAt(): string
 * @method setCreatedAt(string $createdAt)
 * @method getLastEditBy(): string
 * @method setLastEditBy(string $lastEditBy)
 * @method getLastEditAt(): string
 * @method setLastEditAt(string $lastEditAt)
 * @method getData(): string
 * @method setData(string $data)
 */
class LegacyRow extends Entity implements JsonSerializable {
	protected ?int $tableId = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;

	protected ?string $data = null;

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
			'data' => $this->getDataArray(),
		];
	}

	public function getDataArray():array {
		return \json_decode($this->getData(), true);
	}

	public function setDataArray(array $array):void {
		$new = [];
		foreach ($array as $a) {
			$new[] = [
				'columnId' => (int)$a['columnId'],
				'value' => $a['value']
			];
		}
		$json = \json_encode($new);
		$this->setData($json);
	}
}
