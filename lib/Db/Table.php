<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;
use OCA\Tables\Model\Permissions;
use OCA\Tables\Model\SortRuleSet;
use OCA\Tables\ResponseDefinitions;
use OCA\Tables\Service\ValueObject\ColumnOrderInformation;

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
 * @method getViews(): ?array<TablesView>
 * @method setViews(array $views)
 * @method getColumns(): array
 * @method setColumns(array $columns)
 * @method getColumnOrder(): ?string
 * @method setColumnOrder(?string $columnOrder)
 * @method getSort(): ?string
 * @method setSort(?string $sort)
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

	protected ?string $columnOrder = null; // json
	protected ?string $sort = null; // json

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
			'favorite' => (bool)$this->favorite,
			'onSharePermissions' => $this->getSharePermissions()?->jsonSerialize(),
			'hasShares' => (bool)$this->hasShares,
			'rowsCount' => $this->rowsCount ?: 0,
			'columnsCount' => $this->columnsCount ?: 0,
			'description' => $this->description ?: '',
			'views' => array_values($this->getViewsArray()),
			'columnOrder' => array_map(
				static fn (ColumnOrderInformation $c) => ['columnId' => $c->getId(), 'order' => $c->getOrder()],
				$this->getColumnOrderSettingsArray()
			),
			'sort' => $this->getSortArray(),
		];
	}

	private function getSharePermissions(): ?Permissions {
		return $this->onSharePermissions;
	}

	/**
	 * @return TablesView[]
	 */
	private function getViewsArray(): array {
		return $this->getViews() ?: [];
	}

	/**
	 * @return int[]
	 */
	public function getColumnOrderArray(): array {
		$columnSettings = $this->getColumnOrderSettingsArray();
		usort($columnSettings, static function (ColumnOrderInformation $a, ColumnOrderInformation $b) {
			return $a->getOrder() - $b->getOrder();
		});
		/** @var list<ColumnOrderInformation> $columnSettings */
		return array_map(static fn (ColumnOrderInformation $vci): int => $vci->getId(), $columnSettings);
	}

	/**
	 * @return list<ColumnOrderInformation>
	 */
	public function getColumnOrderSettingsArray(): array {
		$columns = $this->getArray($this->getColumnOrder());
		if (empty($columns)) {
			return [];
		}

		if (is_array($columns[array_key_first($columns)] ?? null)) {
			return array_values(array_map(static fn (array $a): ColumnOrderInformation => ColumnOrderInformation::fromArray($a), $columns));
		}

		$result = [];
		foreach ($columns as $index => $columnId) {
			$result[] = new ColumnOrderInformation((int)$columnId, order: (int)$index + 1);
		}
		return $result;
	}

	/**
	 * @return list<array{columnId: int, mode: 'ASC'|'DESC'}>
	 */
	public function getSortArray(): array {
		$rawSortRules = $this->getArray($this->getSort());
		return SortRuleSet::createFromInputArray($rawSortRules)->jsonSerialize();
	}

	private function getArray(?string $json): array {
		if ($json !== '' && $json !== null && $json !== 'null') {
			return \json_decode($json, true) ?? [];
		}
		return [];
	}
}
