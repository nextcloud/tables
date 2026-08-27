<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use JsonSerializable;

class ContextScheme implements JsonSerializable {

	public function __construct(protected ?string $name, protected ?string $icon, protected ?string $description, protected ?array $nodes = [], protected ?array $pages = [], protected ?array $tables = [])
    {
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
