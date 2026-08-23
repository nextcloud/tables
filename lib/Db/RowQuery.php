<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Db;

use InvalidArgumentException;
use OCA\Tables\Helper\ConversionHelper;
use OCA\Tables\Model\FilterSet;
use OCA\Tables\Model\SortRuleSet;

class RowQuery {
	protected ?string $userId = null;
	protected ?int $limit = null;
	protected ?int $offset = null;
	protected ?array $filter = null;
	protected ?array $sort = null;
	protected ?string $search = null;
	protected ?array $rowIds = null;

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

	public function getSearch(): ?string {
		return $this->search;
	}

	public function setSearch(?string $search): self {
		$this->search = $search;
		return $this;
	}

	public function getRowIds(): ?array {
		return $this->rowIds;
	}

	public function setRowIds(?array $rowIds): self {
		$this->rowIds = $rowIds;
		return $this;
	}

	/**
	 * Build a RowQuery from request parameters.
	 *
	 * @param bool $normalizePagination Clamp limit and offset to valid ranges instead of throwing.
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function buildFromInput(string $nodeType, int $nodeId, string $userId, ?int $limit = null, ?int $offset = null, ?string $filter = null, ?string $sort = null, ?string $search = null, ?string $rowIds = null, bool $normalizePagination = false): self {
		if ($normalizePagination) {
			$limit = $limit !== null ? max(0, min(500, $limit)) : null;
			$offset = $offset !== null ? max(0, $offset) : null;
		}

		$rowQuery = new self(ConversionHelper::stringNodeType2Const($nodeType), $nodeId);
		$rowQuery->setLimit($limit)
			->setOffset($offset)
			->setFilter(self::parseFilter($filter))
			->setSort(self::parseSort($sort))
			->setSearch($search !== '' && $search !== null ? $search : null)
			->setRowIds(self::parseRowIds($rowIds))
			->setUserId($userId);
		return $rowQuery;
	}

	/**
	 * Decode and validate the JSON encoded row ID list.
	 *
	 * @return list<int>|null
	 * @throws InvalidArgumentException
	 */
	private static function parseRowIds(?string $rowIds): ?array {
		if ($rowIds === null || $rowIds === '') {
			return null;
		}
		$decoded = json_decode($rowIds, true);
		if (!is_array($decoded) || array_filter($decoded, 'is_int') !== $decoded) {
			throw new InvalidArgumentException('Invalid row IDs supplied');
		}
		return array_map('intval', $decoded);
	}

	/**
	 * Decode and validate the JSON encoded filter parameter.
	 *
	 * @return list<list<array{columnId: int, operator: string, value: string|int|float|list<string|int>}>>|null
	 * @throws InvalidArgumentException
	 */
	private static function parseFilter(?string $filter): ?array {
		if ($filter === null || $filter === '') {
			return null;
		}
		$decoded = json_decode($filter, true);
		if (!is_array($decoded)) {
			throw new InvalidArgumentException('Invalid filter supplied');
		}
		return FilterSet::createFromInputArray($decoded)->jsonSerialize();
	}

	/**
	 * Decode and validate the JSON encoded sort parameter.
	 *
	 * @return list<array{columnId: int, mode: 'ASC'|'DESC'}>|null
	 * @throws InvalidArgumentException
	 */
	private static function parseSort(?string $sort): ?array {
		if ($sort === null || $sort === '') {
			return null;
		}
		$decoded = json_decode($sort, true);
		if (!is_array($decoded)) {
			throw new InvalidArgumentException('Invalid sort data supplied');
		}
		return SortRuleSet::createFromInputArray($decoded)->jsonSerialize();
	}
}
