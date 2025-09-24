<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\Model\Permissions;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ValueObject\ViewColumnInformation;

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
 * @method getColumns(): string
 * @method setColumns(string $columns)
 * @method getColumnSettings(): string
 * @method setColumnSettings(string $columnSettings)
 * @method getCreatedBy(): string
 * @method setCreatedBy(string $createdBy)
 * @method getCreatedAt(): string
 * @method setCreatedAt(string $createdAt)
 * @method getFilter(): string
 * @method setFilter(string $filter)
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
 * @method getOnSharePermissions(): ?Permissions
 * @method setOnSharePermissions(Permissions $onSharePermissions)
 * @method getHasShares(): bool
 * @method setHasShares(bool $hasShares)
 * @method getFavorite(): bool
 * @method setFavorite(bool $favorite)
 * @method getRowsCount(): int
 * @method setRowsCount(int $rowCount)
 * @method getSort(): string
 * @method setSort(string $sort)
 * @method getOwnerDisplayName(): string
 * @method setOwnerDisplayName(string $ownerDisplayName)
 * @method getOwnership(): ?string
 * @method setOwnership(string $ownership)
 */
class View extends EntitySuper implements JsonSerializable {
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

	// virtual properties
	protected ?bool $isShared = null;
	protected ?Permissions $onSharePermissions = null;
	protected ?bool $hasShares = false;
	protected bool $favorite = false;
	protected ?int $rowsCount = 0;
	protected ?string $ownership = null;
	protected ?string $ownerDisplayName = null;

	protected const VIRTUAL_PROPERTIES = ['isShared', 'onSharePermissions', 'hasShares', 'favorite', 'rowsCount', 'ownership', 'ownerDisplayName'];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('tableId', 'integer');
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return int[]
	 */
	public function getColumnsArray(): array {

		$columnSettings = $this->getColumnsSettingsArray();
		usort($columnSettings, static function (ViewColumnInformation $a, ViewColumnInformation $b) {
			return $a->getOrder() - $b->getOrder();
		});
		return array_column($columnSettings, ViewColumnInformation::KEY_ID);
	}

	/**
	 * @return array<ViewColumnInformation>
	 */
	public function getColumnsSettingsArray(): array {
		$columns = $this->getArray($this->getColumns());
		if (empty($columns)) {
			return [];
		}

		if (is_array(reset($columns))) {
			return array_map(static fn (array $a): ViewColumnInformation => ViewColumnInformation::fromArray($a), $columns);
		}

		$result = [];
		foreach ($columns as $index => $columnId) {
			$result[] = new ViewColumnInformation($columnId, order: (int)$index + 1);
		}
		return $result;
	}

	public function findColumnSettingsForColumn(int $columnId): ?ViewColumnInformation {
		foreach ($this->getColumnsSettingsArray() as $columnSettings) {
			if ($columnSettings->getId() === $columnId) {
				return $columnSettings;
			}
		}
		return null;
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
	 * @return list<list<array{columnId: int, operator: 'begins-with'|'ends-with'|'contains'|'does-not-contain'|'is-equal'|'is-not-equal'|'is-greater-than'|'is-greater-than-or-equal'|'is-lower-than'|'is-lower-than-or-equal'|'is-empty', value: string|int|float}>>
	 */
	public function getFilterArray():array {
		$filters = $this->getArray($this->getFilter());
		// a filter(group) was stored with a not-selected column - it may break impressively.
		// filter them out now until we have a permanent fix
		foreach ($filters as &$filterGroups) {
			$filterGroups = array_filter($filterGroups, function (array $item) {
				return $item['columnId'] !== null;
			});
		}
		return array_filter($filters, function (array $item) {
			return !empty($item);
		});
	}

	private function getArray(?string $json): array {
		if ($json !== '' && $json !== null && $json !== 'null') {
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

	private function getSharePermissions(): ?Permissions {
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
			'columnSettings' => $this->getColumnsSettingsArray(),
			'sort' => $this->getSortArray(),
			'isShared' => (bool)$this->isShared,
			'favorite' => $this->favorite,
			'onSharePermissions' => $this->getSharePermissions()?->jsonSerialize(),
			'hasShares' => (bool)$this->hasShares,
			'rowsCount' => $this->rowsCount ?: 0,
			'ownerDisplayName' => $this->ownerDisplayName,
		];
		$serialisedJson['filter'] = $this->getFilterArray();

		return $serialisedJson;
	}

	/**
	 * @psalm-suppress MismatchingDocblockReturnType
	 * @return int[]
	 */
	public function getColumnIds(): array {
		$columns = $this->getColumnsSettingsArray();
		return array_map(static fn (ViewColumnInformation $column): int => $column[ViewColumnInformation::KEY_ID], $columns);
	}
}
