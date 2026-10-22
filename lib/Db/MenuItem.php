<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;

/**
 * An item of the menu of an application. It opens a view, a table, a page of the
 * application or a link, and may sit under a top-level item.
 *
 * @method getContextId(): int
 * @method setContextId(int $contextId): void
 * @method getLabel(): string
 * @method setLabel(string $label): void
 * @method getIcon(): ?string
 * @method setIcon(?string $icon): void
 * @method getDescription(): ?string
 * @method setDescription(?string $description): void
 * @method getTargetType(): string
 * @method setTargetType(string $targetType): void
 * @method getTargetId(): ?int
 * @method setTargetId(?int $targetId): void
 * @method getUrl(): ?string
 * @method setUrl(?string $url): void
 * @method getTechnicalName(): string
 * @method setTechnicalName(string $technicalName): void
 * @method getOrder(): int
 * @method setOrder(int $order): void
 * @method getParentId(): ?int
 * @method setParentId(?int $parentId): void
 * @method isBroken(): bool
 * @method setBroken(bool $broken): void
 */
class MenuItem extends EntitySuper implements JsonSerializable {
	public const TARGET_VIEW = 'view';
	public const TARGET_TABLE = 'table';
	public const TARGET_URL = 'url';
	public const TARGET_PAGE = 'page';
	public const TARGET_TYPES = [self::TARGET_VIEW, self::TARGET_TABLE, self::TARGET_URL, self::TARGET_PAGE];
	public const DEFAULT_NAME_PREFIX = 'item_';
	public const MAX_ITEMS_PER_APPLICATION = 200;

	protected ?int $contextId = null;
	protected ?string $label = null;
	protected ?string $icon = null;
	protected ?string $description = null;
	protected ?string $targetType = null;
	protected ?int $targetId = null;
	protected ?string $url = null;
	protected ?string $technicalName = null;
	protected ?int $order = null;
	protected ?int $parentId = null;

	/** True when the view, table or page the item opens is gone; not stored. */
	protected bool $broken = false;

	protected const VIRTUAL_PROPERTIES = ['broken'];

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('contextId', 'integer');
		$this->addType('targetId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('parentId', 'integer');
		$this->addType('broken', 'boolean');
	}

	/**
	 * Whether a technical name has the form of the automatic `item_<id>`. Such names are
	 * reserved, so a given name never collides with the name a later item gets.
	 */
	public static function isDefaultName(string $technicalName): bool {
		return preg_match('/^' . self::DEFAULT_NAME_PREFIX . '[0-9]+$/', $technicalName) === 1;
	}

	/**
	 * @return array{id: int, contextId: int, label: string, icon: string|null, description: string|null, targetType: 'view'|'table'|'url'|'page', targetId: int|null, url: string|null, technicalName: string, order: int, parentId: int|null, broken: bool}
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contextId' => $this->getContextId(),
			'label' => $this->getLabel() ?? '',
			'icon' => $this->getIcon(),
			'description' => $this->getDescription(),
			'targetType' => match ($this->targetType) {
				self::TARGET_VIEW => self::TARGET_VIEW,
				self::TARGET_TABLE => self::TARGET_TABLE,
				self::TARGET_PAGE => self::TARGET_PAGE,
				default => self::TARGET_URL,
			},
			'targetId' => $this->getTargetId(),
			'url' => $this->getUrl(),
			'technicalName' => $this->technicalName ?? '',
			'order' => $this->getOrder() ?? 0,
			'parentId' => $this->getParentId(),
			'broken' => $this->broken,
		];
	}
}
