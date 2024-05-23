<?php

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @psalm-import-type TablesTable from ResponseDefinitions
 * @psalm-import-type TablesView from ResponseDefinitions
 *
 * @method getTitle(): string
 * @method setTitle(string $title)
 * @method getEmoji(): string
 * @method setEmoji(string $emoji)
 * @method getArchived(): bool
 * @method setArchived(bool $archived)
 * @method setDescription(string $description)
 * @method getOwnership(): string
 * @method setOwnership(string $ownership)
 * @method getOwnerDisplayName(): string
 * @method setOwnerDisplayName(string $ownerDisplayName)
 * @method getIsShared(): bool
 * @method setIsShared(bool $isShared)
 * @method getOnSharePermissions(): array
 * @method setOnSharePermissions(array $onSharePermissions)
 * @method getHasShares(): bool
 * @method setHasShares(bool $hasShares)
 * @method getFavorite(): bool
 * @method setFavorite(bool $favorite)
 * @method getRowsCount(): int
 * @method setRowsCount(int $rowsCount)
 * @method getColumnsCount(): int
 * @method setColumnsCount(int $columnsCount)
 * @method getViews(): array
 * @method setViews(array $views)
 * @method getColumns(): array
 * @method setColumns(array $columns)
 * @method getCreatedBy(): string
 * @method setCreatedBy(string $createdBy)
 * @method getCreatedAt(): string
 * @method setCreatedAt(string $createdAt)
 * @method getLastEditBy(): string
 * @method setLastEditBy(string $lastEditBy)
 * @method getLastEditAt(): string
 * @method setLastEditAt(string $lastEditAt)
 */
class Table extends Entity implements JsonSerializable {
	protected ?string $title = null;
	protected ?string $emoji = null;
	protected ?string $ownership = null;
	protected ?string $ownerDisplayName = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;
	protected bool $archived = false;
	protected ?bool $isShared = null;
	protected ?array $onSharePermissions = null;

	protected ?bool $hasShares = false;
	protected ?bool $favorite = false;
	protected ?int $rowsCount = 0;
	protected ?int $columnsCount = 0;
	protected ?array $views = null;
	protected ?array $columns = null;
	protected ?string $description = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('archived', 'boolean');
	}

	/**
	 * @psalm-return TablesTable
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title ?: '',
			'emoji' => $this->emoji,
			'ownership' => $this->ownership ?: '',
			'ownerDisplayName' => $this->ownerDisplayName ?: '',
			'createdBy' => $this->createdBy ?: '',
			'createdAt' => $this->createdAt ?: '',
			'lastEditBy' => $this->lastEditBy ?: '',
			'lastEditAt' => $this->lastEditAt ?: '',
			'archived' => $this->archived,
			'isShared' => !!$this->isShared,
			'favorite' => $this->favorite,
			'onSharePermissions' => $this->getSharePermissions(),
			'hasShares' => !!$this->hasShares,
			'rowsCount' => $this->rowsCount ?: 0,
			'columnsCount' => $this->columnsCount ?: 0,
			'views' => $this->getViewsArray(),
			'description' => $this->description ?:'',
		];
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return array{read: bool, create: bool, update: bool, delete: bool, manage: bool}|null
	 */
	private function getSharePermissions(): ?array {
		return $this->onSharePermissions;
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return TablesView[]
	 */
	private function getViewsArray(): array {
		return $this->getViews() ?: [];
	}
}
