<?php

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
 * @method getCreatedBy(): string
 * @method setCreatedBy(string $createdBy)
 * @method getCreatedAt(): string
 * @method setCreatedAt(string $createdAt)
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
	 * @param float|null|string $value
	 */
	public function jsonSerializePreparation(string|float|null $value): array {
		return [
			'id' => $this->id,
			'columnId' => $this->columnId,
			'rowId' => $this->rowId,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
			'value' => $value
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
