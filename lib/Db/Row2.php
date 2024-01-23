<?php

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\ResponseDefinitions;

/**
 * @psalm-import-type TablesRow from ResponseDefinitions
 */
class Row2 implements JsonSerializable {
	private ?int $id = null;
	private ?int $tableId = null;
	private ?string $createdBy = null;
	private ?string $createdAt = null;
	private ?string $lastEditBy = null;
	private ?string $lastEditAt = null;
	private ?array $data = [];
	private array $changedColumnIds = []; // collect column ids that have changed after $loaded = true

	private bool $loaded = false; // set to true if model is loaded, after that changed column ids will be collected

	public function getId(): ?int {
		return $this->id;
	}
	public function setId(int $id): void {
		$this->id = $id;
	}

	public function getTableId(): ?int {
		return $this->tableId;
	}
	public function setTableId(int $tableId): void {
		$this->tableId = $tableId;
	}

	public function getCreatedBy(): ?string {
		return $this->createdBy;
	}
	public function setCreatedBy(string $userId): void {
		$this->createdBy = $userId;
	}

	public function getCreatedAt(): ?string {
		return $this->createdAt;
	}
	public function setCreatedAt(string $time): void {
		$this->createdAt = $time;
	}

	public function getLastEditBy(): ?string {
		return $this->lastEditBy;
	}
	public function setLastEditBy(string $userId): void {
		$this->lastEditBy = $userId;
	}

	public function getLastEditAt(): ?string {
		return $this->lastEditAt;
	}
	public function setLastEditAt(string $time): void {
		$this->lastEditAt = $time;
	}

	public function getData(): ?array {
		return $this->data;
	}

	/**
	 * @param list<array{columnId: int, value: mixed}> $data
	 * @return void
	 */
	public function setData(array $data): void {
		foreach ($data as $cell) {
			$this->insertOrUpdateCell($cell);
		}
	}

	/**
	 * @param int $columnId
	 * @param int|float|string $value
	 * @return void
	 */
	public function addCell(int $columnId, $value) {
		$this->data[] = ['columnId' => $columnId, 'value' => $value];
		$this->addChangedColumnId($columnId);
	}

	/**
	 * @param array{columnId: int, value: mixed} $entry
	 * @return string
	 */
	public function insertOrUpdateCell(array $entry): string {
		$columnId = $entry['columnId'];
		$value = $entry['value'];
		foreach ($this->data as &$cell) {
			if($cell['columnId'] === $columnId) {
				if ($cell['value'] != $value) { // yes, no type safety here
					$cell['value'] = $value;
					$this->addChangedColumnId($columnId);
				}
				return 'updated';
			}
		}
		$this->data[] = ['columnId' => $columnId, 'value' => $value];
		$this->addChangedColumnId($columnId);
		return 'inserted';
	}

	public function removeCell(int $columnId): void {
		// TODO
	}

	/**
	 * @psalm-return TablesRow
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'tableId' => $this->tableId,
			'data' => $this->data,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
		];
	}

	/**
	 * Can only be changed by private methods
	 * @param int $columnId
	 * @return void
	 */
	private function addChangedColumnId(int $columnId): void {
		if ($this->loaded && !in_array($columnId, $this->changedColumnIds)) {
			$this->changedColumnIds[] = $columnId;
		}
	}

	/**
	 * @return list<array{columnId: int, value: mixed}>
	 */
	public function getChangedCells(): array {
		$out = [];
		foreach ($this->data as $cell) {
			if (in_array($cell['columnId'], $this->changedColumnIds)) {
				$out[] = $cell;
			}
		}
		return $out;
	}

	/**
	 * Set loaded status to true
	 * starting now changes will be tracked
	 *
	 * @return void
	 */
	public function markAsLoaded(): void {
		$this->loaded = true;
	}

}
