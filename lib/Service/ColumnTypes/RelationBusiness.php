<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
use OCA\Tables\Errors\BadRequestError;
use OCA\Tables\Service\RelationService;
use Psr\Log\LoggerInterface;

class RelationBusiness extends SuperBusiness implements IColumnTypeBusiness {

	public function __construct(
		LoggerInterface $logger,
		private readonly RelationService $relationService,
	) {
		parent::__construct($logger);
	}

	/**
	 * @param mixed $value (array|string|int|null)
	 * @param Column|null $column
	 *
	 * @return false|string
	 */
	public function parseValue($value, ?Column $column = null): string|false {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . self::class, ['exception' => new \Exception()]);
			return json_encode([]);
		}

		$ids = $this->normalizeToIds($value, $column);
		return json_encode($ids);
	}

	/**
	 * @param mixed $value (array|string|int|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . self::class, ['exception' => new \Exception()]);
			return false;
		}
		if ($value === null || $value === '' || $value === []) {
			return true;
		}

		try {
			$this->normalizeToIds($value, $column, throwOnInvalid: true);
			return true;
		} catch (BadRequestError) {
			return false;
		}
	}

	/**
	 * Import / display parsing keeps valid relation targets when some labels fail.
	 */
	public function canBeParsedDisplayValue($value, Column $column): bool {
		if ($value === null || $value === '' || $value === []) {
			return true;
		}

		return $this->normalizeToIds($value, $column, throwOnInvalid: false) !== [];
	}

	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		if ($value === null || $value === '' || $value === []) {
			// Emptiness for mandatory columns is enforced in RowService::validateMandatoryColumns()
			// (including view-specific mandatory settings).
			return;
		}

		$ids = $this->normalizeToIds($value, $column, throwOnInvalid: true);
		$allowMultiple = (bool)($column->getCustomSettingsArray()[Column::RELATION_ALLOW_MULTIPLE] ?? false);

		if (!$allowMultiple && count($ids) > 1) {
			throw new BadRequestError('Relation column does not allow multiple values');
		}
	}

	/**
	 * Resolve labels/ids into a de-duplicated list of related row ids.
	 *
	 * Accepts:
	 * - null / '' / [] → []
	 * - single int/string id or label (legacy single-value clients)
	 * - array of ints/strings (ids or labels)
	 * - JSON-encoded id / list of ids
	 *
	 * Comma-separated strings are only expanded when the full string is not a
	 * valid label (labels may contain commas).
	 *
	 * @return list<int>
	 * @throws BadRequestError
	 */
	private function normalizeToIds(mixed $value, Column $column, bool $throwOnInvalid = false): array {
		if ($value === null || $value === '' || $value === []) {
			return [];
		}

		if (is_string($value)) {
			$decoded = json_decode($value, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				$value = $decoded;
			} elseif (ctype_digit($value)) {
				// Keep numeric ids as ints so resolveRelationId does not depend on string equality
				$value = [(int)$value];
			} else {
				// Keep as a single token first — labels may contain commas
				$value = [$value];
			}
		}

		if (!is_array($value)) {
			$value = [$value];
		}

		$relationData = $this->relationService->getRelationData($column);
		$ids = [];

		foreach ($value as $item) {
			if ($item === null || $item === '') {
				continue;
			}

			$resolvedIds = $this->resolveRelationIds($item, $relationData, $throwOnInvalid);
			foreach ($resolvedIds as $resolvedId) {
				$ids[] = $resolvedId;
			}
		}

		return array_values(array_unique($ids, SORT_NUMERIC));
	}

	/**
	 * @param array<int|string, array{id: int, label: string}> $relationData
	 * @return list<int>
	 * @throws BadRequestError
	 */
	private function resolveRelationIds(mixed $value, array $relationData, bool $throwOnInvalid): array {
		$resolvedId = $this->resolveRelationId($value, $relationData);
		if ($resolvedId !== null) {
			return [$resolvedId];
		}

		// Only expand comma-separated input when the full string is not a label
		if (is_string($value) && str_contains($value, ',')) {
			$partIds = [];
			foreach (array_map(trim(...), explode(',', $value)) as $part) {
				if ($part === '') {
					continue;
				}
				$partId = $this->resolveRelationId($part, $relationData);
				if ($partId === null) {
					if ($throwOnInvalid) {
						throw new BadRequestError('Relation value does not exist in the target table/view');
					}
					continue;
				}
				$partIds[] = $partId;
			}
			return $partIds;
		}

		if ($throwOnInvalid) {
			throw new BadRequestError('Relation value does not exist in the target table/view');
		}
		return [];
	}

	/**
	 * @param array<int|string, array{id: int, label: string}> $relationData
	 */
	private function resolveRelationId(mixed $value, array $relationData): ?int {
		// Prefer numeric id match so stringified ints from clients resolve reliably
		if (is_int($value) || (is_string($value) && ctype_digit($value))) {
			$id = (int)$value;
			if (isset($relationData[$id])) {
				return $id;
			}
		}

		$matchingRelation = array_filter($relationData, fn (array $relation) => $relation['label'] === $value);
		if (!empty($matchingRelation)) {
			return (int)reset($matchingRelation)['id'];
		}

		return null;
	}
}
