<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * One entry of an application's menu. It points at a view, a table or a URL.
 *
 * @method getContextId(): int
 * @method setContextId(int $contextId): void
 * @method getLabel(): string
 * @method setLabel(string $label): void
 * @method getIcon(): ?string
 * @method setIcon(?string $icon): void
 * @method getTargetType(): string
 * @method setTargetType(string $targetType): void
 * @method getTargetId(): ?int
 * @method setTargetId(?int $targetId): void
 * @method getUrl(): ?string
 * @method setUrl(?string $url): void
 * @method getTechnicalName(): ?string
 * @method setTechnicalName(?string $technicalName): void
 * @method getOrder(): int
 * @method setOrder(int $order): void
 */
class MenuItem extends Entity implements JsonSerializable {
	public const TARGET_VIEW = 'view';
	public const TARGET_TABLE = 'table';
	public const TARGET_URL = 'url';
	public const TARGET_TYPES = [self::TARGET_VIEW, self::TARGET_TABLE, self::TARGET_URL];

	protected ?int $contextId = null;
	protected ?string $label = null;
	protected ?string $icon = null;
	protected ?string $targetType = null;
	protected ?int $targetId = null;
	protected ?string $url = null;
	protected ?string $technicalName = null;
	protected ?int $order = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('contextId', 'integer');
		$this->addType('targetId', 'integer');
		$this->addType('order', 'integer');
	}

	/**
	 * @return array{id: int, contextId: int, label: string, icon: string|null, targetType: string, targetId: int|null, url: string|null, technicalName: string|null, order: int}
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'contextId' => $this->getContextId(),
			'label' => $this->getLabel() ?? '',
			'icon' => $this->getIcon(),
			'targetType' => $this->getTargetType() ?? self::TARGET_URL,
			'targetId' => $this->getTargetId(),
			'url' => $this->getUrl(),
			'technicalName' => $this->getTechnicalName(),
			'order' => $this->getOrder() ?? 0,
		];
	}
}
