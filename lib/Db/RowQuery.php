<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Tables\Db;

class RowQuery {
	protected ?string $userId = null;
	protected ?int $limit = null;
	protected ?int $offset = null;
	protected ?array $filter = null;
	protected ?array $sort = null;

	public function __construct(
		protected int $nodeType,
		protected int $nodeId,
	) {
	}

	public function getNodeType(): int {
		return $this->nodeType;
	}

	public function getNodeId(): int {
		return $this->nodeId;
	}

	public function getUserId(): ?string {
		return $this->userId;
	}

	public function setUserId(?string $userId): self {
		$this->userId = $userId;
		return $this;
	}

	public function getLimit(): ?int {
		return $this->limit;
	}

	public function setLimit(?int $limit): self {
		$this->limit = $limit;
		return $this;
	}

	public function getOffset(): ?int {
		return $this->offset;
	}

	public function setOffset(?int $offset): self {
		$this->offset = $offset;
		return $this;
	}

	public function getFilter(): ?array {
		return $this->filter;
	}

	public function setFilter(?array $filter): self {
		$this->filter = $filter;
		return $this;
	}

	public function getSort(): ?array {
		return $this->sort;
	}

	public function setSort(?array $sort): self {
		$this->sort = $sort;
		return $this;
	}
}
