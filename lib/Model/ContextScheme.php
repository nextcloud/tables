<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use InvalidArgumentException;
use JsonSerializable;

class ContextScheme implements JsonSerializable {

	public function __construct(
		protected ?string $name,
		protected ?string $icon,
		protected ?string $description,
		protected ?array $nodes = [],
		protected ?array $pages = [],
		protected ?array $tables = [],
		protected ?string $technicalName = null,
		protected array $menuItems = [],
		protected array $gridViews = [],
	) {
	}

	/**
	 * Builds the scheme from an import request and refuses what the import could not handle.
	 *
	 * @param array<string, mixed> $data
	 * @throws InvalidArgumentException
	 */
	public static function createFromInputArray(array $data): self {
		$tables = $data['tables'] ?? null;
		if (!is_array($tables) || !isset($tables['addTables'], $tables['modifyTables']) || !is_array($tables['addTables']) || !is_array($tables['modifyTables'])) {
			throw new InvalidArgumentException('Invalid tables structure provided: expected addTables and modifyTables lists.');
		}
		foreach (['nodes', 'menuItems', 'gridViews'] as $list) {
			if (isset($data[$list]) && !is_array($data[$list])) {
				throw new InvalidArgumentException('The scheme property "' . $list . '" must be a list.');
			}
		}
		if (!is_string($data['name'] ?? null) || trim($data['name']) === '') {
			throw new InvalidArgumentException('A scheme needs a name.');
		}
		return new self(
			name: $data['name'],
			icon: isset($data['icon']) ? (string)$data['icon'] : null,
			description: isset($data['description']) ? (string)$data['description'] : null,
			nodes: array_values($data['nodes'] ?? []),
			pages: [],
			tables: $tables,
			technicalName: isset($data['technicalName']) && $data['technicalName'] !== '' ? (string)$data['technicalName'] : null,
			menuItems: array_values($data['menuItems'] ?? []),
			gridViews: array_values($data['gridViews'] ?? []),
		);
	}

	public function getTechnicalName(): ?string {
		return $this->technicalName;
	}

	/**
	 * Menu items point at their target by uuid, so a scheme can be imported on another instance.
	 */
	public function getMenuItems(): array {
		return $this->menuItems;
	}

	/**
	 * Views without a table that the menu points at; they live outside the tables of the scheme.
	 */
	public function getGridViews(): array {
		return $this->gridViews;
	}

	public function getName(): ?string {
		return $this->name ?? '';
	}

	public function getIcon(): ?string {
		return $this->icon;
	}

	public function getDescription(): ?string {
		return $this->description;
	}

	public function getNodes(): ?array {
		return $this->nodes;
	}

	public function getPages(): ?array {
		return $this->pages;
	}

	public function getTables(): ?array {
		return $this->tables;
	}

	public function jsonSerialize(): mixed {
		return [
			'name' => $this->name,
			'icon' => $this->icon,
			'description' => $this->description,
			'nodes' => $this->nodes,
			'pages' => $this->pages,
			'tables' => $this->tables,
			'technicalName' => $this->technicalName,
			'menuItems' => $this->menuItems,
			'gridViews' => $this->gridViews,
		];
	}
}
