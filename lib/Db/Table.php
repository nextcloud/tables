<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\Model\Permissions;
use OCA\Tables\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
 * @psalm-import-type TablesTable from ResponseDefinitions
 * @psalm-import-type TablesView from ResponseDefinitions
 *
 * @method string getTitle()
 * @method int getId()
 * @method setTitle(string $title)
 * @method string getEmoji()
 * @method setEmoji(string $emoji)
 * @method bool getArchived()
 * @method setArchived(bool $archived)
 * @method string getDescription()
 * @method setDescription(string $description)
 * @method string getOwnership()
 * @method setOwnership(string $ownership)
 * @method string getOwnerDisplayName()
 * @method setOwnerDisplayName(string $ownerDisplayName)
 * @method bool getIsShared()
 * @method setIsShared(bool $isShared)
 * @method Permissions|null getOnSharePermissions()
 * @method setOnSharePermissions(Permissions $onSharePermissions)
 * @method bool getHasShares()
 * @method setHasShares(bool $hasShares)
 * @method bool getFavorite()
 * @method setFavorite(bool $favorite)
 * @method int getRowsCount()
 * @method setRowsCount(int $rowsCount)
 * @method int getColumnsCount()
 * @method setColumnsCount(int $columnsCount)
 * @method array getViews()
 * @method setViews(array $views)
 * @method array getColumns()
 * @method setColumns(array $columns)
 * @method string getCreatedBy()
 * @method setCreatedBy(string $createdBy)
 * @method string getCreatedAt()
 * @method setCreatedAt(string $createdAt)
 * @method string getLastEditBy()
 * @method setLastEditBy(string $lastEditBy)
 * @method string getLastEditAt()
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
	protected ?Permissions $onSharePermissions = null;

	protected ?bool $hasShares = false;
	protected bool $favorite = false;
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
			'onSharePermissions' => $this->getSharePermissions()?->jsonSerialize(),
			'hasShares' => !!$this->hasShares,
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
	 * @return list<TablesView>
	 */
	private function getViewsArray(): array {
		/** @var list<TablesView> $views */
		$views = $this->getViews() ?: [];
		return $views;
	}
}
