<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\Model\Permissions;
use OCA\Tables\ResponseDefinitions;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @psalm-import-type TablesTable from ResponseDefinitions
 * @psalm-import-type TablesView from ResponseDefinitions
 *
 * @method getTitle(): string
 * @method getId(): int
 * @method setTitle(string $title)
 * @method getEmoji(): string
 * @method setEmoji(string $emoji)
 * @method getArchived(): bool
 * @method setArchived(bool $archived)
 * @method getDescription(): string
 * @method setDescription(string $description)
 * @method getOwnership(): ?string
 * @method setOwnership(string $ownership)
 * @method getOwnerDisplayName(): string
 * @method setOwnerDisplayName(string $ownerDisplayName)
 * @method getIsShared(): bool
 * @method setIsShared(bool $isShared)
 * @method getOnSharePermissions(): ?Permissions
 * @method setOnSharePermissions(Permissions $onSharePermissions)
 * @method getHasShares(): bool
 * @method setHasShares(bool $hasShares)
 * @method getFavorite(): bool
 * @method setFavorite(bool $favorite)
 * @method getRowsCount(): int
 * @method setRowsCount(int $rowsCount)
 * @method getColumnsCount(): int
 * @method setColumnsCount(int $columnsCount)
 * @method getViews(): ?array
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
class Table extends EntitySuper implements JsonSerializable {
	protected ?string $title = null;
	protected ?string $emoji = null;
	protected ?string $ownership = null;
	protected ?string $createdBy = null;
	protected ?string $createdAt = null;
	protected ?string $lastEditBy = null;
	protected ?string $lastEditAt = null;
	protected bool $archived = false;
	protected ?string $description = null;

	// virtual properties
	protected ?bool $isShared = null;
	protected ?Permissions $onSharePermissions = null;
	protected ?bool $hasShares = false;
	protected ?bool $favorite = false;
	protected ?int $rowsCount = 0;
	protected ?int $columnsCount = 0;
	protected ?array $views = null;
	protected ?array $columns = null;
	protected ?string $ownerDisplayName = null;

	protected const VIRTUAL_PROPERTIES = ['isShared', 'onSharePermissions', 'hasShares', 'favorite', 'rowsCount', 'columnsCount', 'views', 'columns', 'ownerDisplayName'];

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
			'isShared' => (bool)$this->isShared,
			'favorite' => $this->favorite,
			'onSharePermissions' => $this->getSharePermissions()?->jsonSerialize(),
			'hasShares' => (bool)$this->hasShares,
			'rowsCount' => $this->rowsCount ?: 0,
			'columnsCount' => $this->columnsCount ?: 0,
			'views' => $this->getViewsArray(),
			'description' => $this->description ?:'',
		];
	}

	private function getSharePermissions(): ?Permissions {
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
