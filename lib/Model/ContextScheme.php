<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use JsonSerializable;

class ContextScheme implements JsonSerializable {

	protected ?string $name = null;
	protected ?string $icon = null;
	protected ?string $description = null;
	protected ?array $nodes = null;
	protected ?array $pages = null;
	protected ?array $tables = null;

	public function __construct(string $name, string $icon, string $description, array $nodes = [], array $pages = [], array $tables = []) {
		$this->name = $name;
		$this->icon = $icon;
		$this->description = $description;
		$this->nodes = $nodes;
		$this->pages = $pages;
		$this->tables = $tables;
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
		];
	}
}
