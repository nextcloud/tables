<?php

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @psalm-import-type TablesView from ResponseDefinitions
 *
 * @method getId(): int
 * @method setId(int $id)
 * @method getTitle(): string
 * @method setTitle(string $title)
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
 * @method getEmoji(): string
 * @method setEmoji(string $emoji)
 * @method getDescription(): string
 * @method setDescription(string $description)
 * @method getIsShared(): bool
 * @method setIsShared(bool $isShared)
 * @method getOnSharePermissions(): array{create: bool,delete: bool,manage: bool,read: bool,update: bool}|null
 * @method setOnSharePermissions(array $onSharePermissions)
 * @method getHasShares(): bool
 * @method setHasShares(bool $hasShares)
 * @method getFavorite(): bool
 * @method setFavorite(bool $favorite)
 * @method getRowsCount(): int
 * @method setRowsCount(int $rowCount)
 * @method getOwnership(): string
 * @method setOwnership(string $ownership)
 * @method getOwnerDisplayName(): string
 * @method setOwnerDisplayName(string $ownerDisplayName)
 */
class View extends Entity implements JsonSerializable {
	protected ?string $title = null;
	protected ?int $tableId = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;
	protected ?string $emoji = null;
	protected ?string $description = null;
	protected ?string $columns = null; // json
	protected ?string $sort = null; // json
	protected ?string $filter = null; // json
	protected ?bool $isShared = null;
	protected ?array $onSharePermissions = null;
	protected ?bool $hasShares = false;
	protected bool $favorite = false;
	protected ?int $rowsCount = 0;
	protected ?string $ownership = null;
	protected ?string $ownerDisplayName = null;


	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return int[]
	 */
	public function getColumnsArray(): array {
		return $this->getArray($this->getColumns());
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return list<array{columnId: int, mode: 'ASC'|'DESC'}>
	 */
	public function getSortArray(): array {
		return $this->getArray($this->getSort());
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return list<list<array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'is-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float}>>
	 */
	public function getFilterArray():array {
		return $this->getArray($this->getFilter());
	}

	private function getArray(?string $json): array {
		if ($json !== "" && $json !== null && $json !== 'null') {
			return \json_decode($json, true);
		} else {
			return [];
		}
	}

	public function setColumnsArray(array $array):void {
		$this->setColumns(\json_encode($array));
	}

	public function setSortArray(array $array):void {
		$this->setSort(\json_encode($array));
	}

	public function setFilterArray(array $array):void {
		$this->setFilter(\json_encode($array));
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return array{create: bool, delete: bool, manage: bool, read: bool, update: bool}|null
	 */
	private function getSharePermissions(): ?array {
		return $this->getOnSharePermissions();
	}

	/**
	 * @psalm-return TablesView
	 */
	public function jsonSerialize(): array {
		$serialisedJson = [
			'id' => $this->id,
			'tableId' => ($this->tableId || $this->tableId === 0) ? $this->tableId : -1,
			'title' => $this->title ?: '',
			'description' => $this->description,
			'emoji' => $this->emoji,
			'ownership' => $this->ownership ?: '',
			'createdBy' => $this->createdBy ?: '',
			'createdAt' => $this->createdAt ?: '',
			'lastEditBy' => $this->lastEditBy ?: '',
			'lastEditAt' => $this->lastEditAt ?: '',
			'columns' => $this->getColumnsArray(),
			'sort' => $this->getSortArray(),
			'isShared' => !!$this->isShared,
			'favorite' => $this->favorite,
			'onSharePermissions' => $this->getSharePermissions(),
			'hasShares' => !!$this->hasShares,
			'rowsCount' => $this->rowsCount ?: 0,
			'ownerDisplayName' => $this->ownerDisplayName,
		];
		$serialisedJson['filter'] = $this->getFilterArray();

		return $serialisedJson;
	}
}
