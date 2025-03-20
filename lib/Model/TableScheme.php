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

	public function __construct(
        protected ?string $title,
        protected ?string $emoji,
        /** @var Column[]|null */
        protected ?array $columns,
        /** @var View[]|null */
        protected ?array $views,
        protected ?string $description,
        protected ?string $tablesVersion
    )
    {
    }

	public function getTitle():string {
		return $this->title | '';
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'title' => $this->title ?: '',
			'emoji' => $this->emoji,
			'columns' => $this->columns,
			'views' => $this->views,
			'description' => $this->description ?:'',
			'tablesVersion' => $this->tablesVersion,
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
