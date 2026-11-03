<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

/**
 * An item of the menu of an application. It opens at most one thing: a page of the
 * application (`page_id`), a table or view of the application (`node_rel_id`), or a
 * link (`url`); at most one of the three is set, which MenuService enforces. An item
 * that opens nothing is a group; groups are top-level and only groups hold items.
 *
 * @method getContextId(): int
 * @method setContextId(int $contextId): void
 * @method getParentId(): ?int
 * @method setParentId(?int $parentId): void
 * @method getOrder(): int
 * @method setOrder(int $order): void
 * @method getTitle(): ?string
 * @method setTitle(?string $title): void
 * @method getIcon(): ?string
 * @method setIcon(?string $icon): void
 * @method getDescription(): ?string
 * @method setDescription(?string $description): void
 * @method getPageId(): ?int
 * @method setPageId(?int $pageId): void
 * @method getNodeRelId(): ?int
 * @method setNodeRelId(?int $nodeRelId): void
 * @method getUrl(): ?string
 * @method setUrl(?string $url): void
 */
class MenuItem extends Entity {
	public const MAX_ITEMS_PER_APPLICATION = 200;
	public const TITLE_MAX_LENGTH = 200;
	public const DESCRIPTION_MAX_LENGTH = 2000;
	public const URL_MAX_LENGTH = 2000;

	protected ?int $contextId = null;
	protected ?int $parentId = null;
	protected ?int $order = null;
	protected ?string $title = null;
	protected ?string $icon = null;
	protected ?string $description = null;
	protected ?int $pageId = null;
	protected ?int $nodeRelId = null;
	protected ?string $url = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('contextId', 'integer');
		$this->addType('parentId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('pageId', 'integer');
		$this->addType('nodeRelId', 'integer');
	}

	public function isGroup(): bool {
		return $this->pageId === null && $this->nodeRelId === null && $this->url === null;
	}

	/**
	 * @return array{pageId: int}|array{nodeRelId: int}|array{url: string}|null
	 */
	public function getTarget(): ?array {
		return match (true) {
			$this->pageId !== null => ['pageId' => $this->pageId],
			$this->nodeRelId !== null => ['nodeRelId' => $this->nodeRelId],
			$this->url !== null => ['url' => $this->url],
			default => null,
		};
	}

	/**
	 * @param array{pageId: int}|array{nodeRelId: int}|array{url: string} $target
	 */
	public function setTarget(array $target): void {
		$this->setPageId($target['pageId'] ?? null);
		$this->setNodeRelId($target['nodeRelId'] ?? null);
		$this->setUrl($target['url'] ?? null);
	}
}
