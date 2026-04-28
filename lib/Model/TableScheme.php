<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use JsonSerializable;
use OCA\Tables\Db\Column;
use OCA\Tables\Db\View;

class TableScheme implements JsonSerializable {

	protected ?string $title = null;
	protected ?string $emoji = null;

	/** @var Column[]|null */
	protected ?array $columns = null;

	/** @var View[]|null */
	protected ?array $views = null;
	protected ?string $description = null;
	protected ?string $tablesVersion = null;
	protected array $columnOrder = [];
	protected array $sort = [];

	public function __construct(string $title, string $emoji, array $columns, array $view, string $description, string $tablesVersion, array $columnOrder = [], array $sort = []) {
		$this->tablesVersion = $tablesVersion;
		$this->title = $title;
		$this->emoji = $emoji;
		$this->columns = $columns;
		$this->description = $description;
		$this->views = $view;
		$this->columnOrder = $columnOrder;
		$this->sort = $sort;
	}

	public function getTitle():string {
		return $this->title | '';
	}

	public function jsonSerialize(): array {
		return [
			'title' => $this->title ?: '',
			'emoji' => $this->emoji,
			'columns' => $this->columns,
			'views' => $this->views,
			'description' => $this->description ?: '',
			'tablesVersion' => $this->tablesVersion,
			'columnOrder' => $this->columnOrder,
			'sort' => $this->sort,
		];
	}

	public function getEmoji(): ?string {
		return $this->emoji;
	}

	public function getColumns(): ?array {
		return $this->columns;
	}

	public function getDescription(): ?string {
		return $this->description;
	}
}
