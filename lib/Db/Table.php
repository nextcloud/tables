<?php

namespace OCA\Tables\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 *
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
 * @method getRowsCount(): int
 * @method setRowsCount(int $rowsCount)
 * @method getBaseView(): View
 * @method setBaseView(View $setBaseView)
 * @method getViews(): array
 * @method setViews(array $setViews)
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
	protected ?bool $isShared = null;
	protected ?array $onSharePermissions = null;

	protected ?bool $hasShares = false;
	protected ?int $rowsCount = 0;
	protected ?View $baseView = null;
	protected ?array $views = null;

	public function __construct() {
		$this->addType('id', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'ownership' => $this->ownership,
			'ownerDisplayName' => $this->ownerDisplayName,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'lastEditBy' => $this->lastEditBy,
			'lastEditAt' => $this->lastEditAt,
			'isShared' => !!$this->isShared,
			'onSharePermissions' => $this->onSharePermissions,
			'hasShares' => $this->hasShares,
			'rowsCount' => $this->rowsCount,
			'baseView' => $this->baseView,
			'views' => $this->views,
		];
	}
}
