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

		try {
			$this->normalizeToIds($value, $column, throwOnInvalid: true);
			return true;
		} catch (BadRequestError) {
			return $this->normalizeToIds($value, $column, throwOnInvalid: false) !== [];
		}
	}

	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		$ids = ($value === null || $value === '' || $value === [])
			? []
			: $this->normalizeToIds($value, $column, throwOnInvalid: true);

		if ($column->getMandatory() && $ids === []) {
			throw new BadRequestError('Relation column is mandatory and cannot be empty');
		}

		if ($ids === []) {
			return;
		}

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
	 * - comma-separated string of labels/ids (import)
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
			} elseif (str_contains($value, ',')) {
				$value = array_map(trim(...), explode(',', $value));
			} else {
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

			$resolvedId = $this->resolveRelationId($item, $relationData);
			if ($resolvedId === null) {
				if ($throwOnInvalid) {
					throw new BadRequestError('Relation value does not exist in the target table/view');
				}
				continue;
			}
			$ids[] = $resolvedId;
		}

		return array_values(array_unique($ids, SORT_NUMERIC));
	}

	/**
	 * @param array<int|string, array{id: int, label: string}> $relationData
	 */
	private function resolveRelationId(mixed $value, array $relationData): ?int {
		// Match by label first (import / human-friendly input)
		$matchingRelation = array_filter($relationData, fn (array $relation) => $relation['label'] === $value);
		if (!empty($matchingRelation)) {
			return (int)reset($matchingRelation)['id'];
		}

		if (is_numeric($value) && isset($relationData[(int)$value])) {
			return (int)$value;
		}

		return null;
	}
}
