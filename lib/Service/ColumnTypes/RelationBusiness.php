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
		private RelationService $relationService,
	) {
		parent::__construct($logger);
	}

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return string
	 */
	public function parseValue($value, ?Column $column = null): string {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return '';
		}

		$relationData = $this->relationService->getRelationData($column);
		// try to find value by label
		$matchingRelation = array_filter($relationData, fn (array $relation) => $relation['label'] === $value);
		if (!empty($matchingRelation)) {
			return json_encode(reset($matchingRelation)['id']);
		}

		// if not found, try to find by id
		if (is_numeric($value) && isset($relationData[(int)$value])) {
			return json_encode($value);
		}

		return '';
	}

	/**
	 * @param mixed $value (array|string|null)
	 * @param Column|null $column
	 * @return bool
	 */
	public function canBeParsed($value, ?Column $column = null): bool {
		if (!$column) {
			$this->logger->warning('No column given, but expected on ' . __FUNCTION__ . ' within ' . __CLASS__, ['exception' => new \Exception()]);
			return false;
		}
		if ($value === null) {
			return true;
		}

		$relationData = $this->relationService->getRelationData($column);
		// try to find value by label
		$matchingRelation = array_filter($relationData, fn (array $relation) => $relation['label'] === $value);
		if (!empty($matchingRelation)) {
			return true;
		}
		// if not found, try to find by id
		if (is_numeric($value) && isset($relationData[(int)$value])) {
			return true;
		}

		return false;
	}

	public function validateValue(mixed $value, Column $column, string $userId, int $tableId, ?int $rowId): void {
		if ($value === null || $value === '') {
			return;
		}
		// Validate that the value exists in the target table/view
		$relationData = $this->relationService->getRelationData($column);

		// Try to find value by label first
		$matchingRelation = array_filter($relationData, fn (array $relation) => $relation['label'] === $value);
		if (!empty($matchingRelation)) {
			return;
		}

		// If not found by label, try to find by id
		if (is_numeric($value) && isset($relationData[(int)$value])) {
			return;
		}

		throw new BadRequestError('Relation value does not exist in the target table/view');
	}
}
