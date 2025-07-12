<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @template T of Entity
 * @psalm-suppress PropertyNotSetInConstructor
 * @method getColumnId(): string
 * @method setColumnId(int $columnId)
 * @method getRowId(): int
 * @method setRowId(int $rowId)
 * @method getValue(): string
 * @method setValue(string $value)
 * @method getLastEditBy(): string
 * @method setLastEditBy(string $lastEditBy)
 * @method getLastEditAt(): string
 * @method setLastEditAt(string $lastEditAt)
 */
abstract class RowCellSuper extends Entity implements JsonSerializable {
	protected ?int $columnId = null;
	protected ?int $rowId = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('columnId', 'integer');
		$this->addType('rowId', 'integer');
	}

	/**
	 * Same as Entity::fromRow but ignoring unknown properties
	 */
	public static function fromRowData(array $row): RowCellSuper {
		$instance = new static();

		foreach ($row as $key => $value) {
			$property = $instance->columnToProperty($key);
			$setter = 'set' . ucfirst($property);
			;
			if (property_exists($instance, $property)) {
				$instance->$setter($value);
			}
		}

		$instance->resetUpdatedFields();

		return $instance;
	}

	/**
	 * @param float|null|string $value
	 * @param int $value_type
	 */
	public function jsonSerializePreparation(string|float|null $value, int $valueType = 0): array {
		return [
			'id' => $this->id,
			'columnId' => $this->columnId,
			'rowId' => $this->rowId,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
			'value' => $value,
			'valueType' => $valueType
		];
	}

	public function setRowIdWrapper(int $rowId) {
		$this->setRowId($rowId);
	}

	public function setColumnIdWrapper(int $columnId) {
		$this->setColumnId($columnId);
	}

	public function setValueWrapper($value) {
		$this->setValue($value);
	}
}
