<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Service\ColumnTypes;

use OCA\Tables\Db\Column;
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

		if (is_array($value) && isset($value['context']) && $value['context'] === 'import') {
			$matchingRelation = array_filter($relationData, fn ($relation) => $relation['label'] === $value['value']);
			if (!empty($matchingRelation)) {
				return json_encode(reset($matchingRelation)['id']);
			}
		} else {
			if (isset($relationData[$value])) {
				return json_encode($relationData[$value]['id']);
			}
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

		if (is_array($value) && isset($value['context']) && $value['context'] === 'import') {
			$matchingRelation = array_filter($relationData, fn ($relation) => $relation['label'] === $value['value']);
			if (!empty($matchingRelation)) {
				return true;
			}
		} else {
			if (isset($relationData[$value])) {
				return true;
			}
		}

		return false;
	}
}
