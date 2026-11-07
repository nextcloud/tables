<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A widget on a grid page: its type, optional title, icon and description, its place
 * on the grid and its settings, stored as one JSON object.
 *
 * @method getPageId(): int
 * @method setPageId(int $value): void
 * @method getType(): string
 * @method setType(string $value): void
 * @method getTitle(): ?string
 * @method setTitle(?string $value): void
 * @method getIcon(): ?string
 * @method setIcon(?string $value): void
 * @method getDescription(): ?string
 * @method setDescription(?string $value): void
 * @method getGridX(): int
 * @method setGridX(int $value): void
 * @method getGridY(): int
 * @method setGridY(int $value): void
 * @method getGridWidth(): int
 * @method setGridWidth(int $value): void
 * @method getGridHeight(): int
 * @method setGridHeight(int $value): void
 * @method getSettings(): ?array
 * @method setSettings(array $value): void
 */
class Widget extends Entity implements \JsonSerializable {
	protected ?int $pageId = null;
	protected ?string $type = null;
	protected ?string $title = null;
	protected ?string $icon = null;
	protected ?string $description = null;
	protected ?int $gridX = null;
	protected ?int $gridY = null;
	protected ?int $gridWidth = null;
	protected ?int $gridHeight = null;
	protected ?array $settings = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('pageId', 'integer');
		$this->addType('gridX', 'integer');
		$this->addType('gridY', 'integer');
		$this->addType('gridWidth', 'integer');
		$this->addType('gridHeight', 'integer');
		$this->addType('settings', Types::JSON);
	}

	/**
	 * The settings as an object, so they serialise as a JSON object also when empty,
	 * ordered by name: a JSON column does not keep the stored order on every database.
	 */
	public function getSettingsObject(): \stdClass {
		$settings = $this->getSettings() ?? [];
		ksort($settings);
		return (object)$settings;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'pageId' => $this->getPageId(),
			'type' => $this->getType(),
			'title' => $this->getTitle(),
			'icon' => $this->getIcon(),
			'description' => $this->getDescription(),
			'gridX' => $this->getGridX(),
			'gridY' => $this->getGridY(),
			'gridWidth' => $this->getGridWidth(),
			'gridHeight' => $this->getGridHeight(),
			'settings' => $this->getSettingsObject(),
		];
	}
}
