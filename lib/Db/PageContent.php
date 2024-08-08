<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method getPageId(): int
 * @method setPageId(int $value): void
 * @method getNodeRelId(): int
 * @method setNodeRelId(int $value): void
 * @method getOrder(): int
 * @method setOrder(int $value): void
 */
class PageContent extends Entity implements \JsonSerializable {
	protected ?int $pageId = null;
	protected ?int $nodeRelId = null;
	protected ?int $order = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('page_id', 'integer');
		$this->addType('node_rel_id', 'integer');
		$this->addType('order', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'pageId' => $this->getPageId(),
			'nodeRelId' => $this->getNodeRelId(),
			'order' => $this->getOrder(),
		];
	}
}
