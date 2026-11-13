<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use OCA\Tables\Db\Widget;

/**
 * A widget as {@see \OCA\Tables\Service\GridWidgetTypes::readWidget()} read it: every
 * field checked against its type. `id` is the widget it changes, null for a new one.
 *
 * @psalm-import-type TablesWidgetSettingValue from Widget
 */
final class WidgetInput {
	/**
	 * @param array<string, TablesWidgetSettingValue> $settings
	 */
	public function __construct(
		public readonly ?int $id,
		public readonly string $type,
		public readonly ?string $title,
		public readonly ?string $icon,
		public readonly ?string $description,
		public readonly int $gridX,
		public readonly int $gridY,
		public readonly int $gridWidth,
		public readonly int $gridHeight,
		public readonly array $settings,
		public readonly ?int $nodeRelId,
	) {
	}

	public function withNodeRelId(?int $nodeRelId): self {
		return new self($this->id, $this->type, $this->title, $this->icon, $this->description, $this->gridX, $this->gridY, $this->gridWidth, $this->gridHeight, $this->settings, $nodeRelId);
	}

	public function withId(?int $id): self {
		return new self($id, $this->type, $this->title, $this->icon, $this->description, $this->gridX, $this->gridY, $this->gridWidth, $this->gridHeight, $this->settings, $this->nodeRelId);
	}
}
