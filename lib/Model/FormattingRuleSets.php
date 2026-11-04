<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

use OCA\Tables\Constants\SelectionReference;
use OCA\Tables\Db\Column;

class FormattingRuleSets {
	public const UNRESOLVED_ID = 0;

	public const UNRESOLVED_OPTION_ID = -1;

	/**
	 * @param array $ruleSets
	 * @param array<int, int> $columnIdMap old column id => new column id
	 * @param array<int, array<int, int>> $selectionOptionIdMap old column id => [old option id => new option id]
	 */
	public static function remapIds(array $ruleSets, array $columnIdMap, array $selectionOptionIdMap): array {
		foreach ($ruleSets as &$ruleSet) {
			$targetCol = $ruleSet['targetCol'] ?? null;
			if ($targetCol !== null && !self::isMetaColumnId($targetCol)) {
				if (isset($columnIdMap[$targetCol])) {
					$ruleSet['targetCol'] = $columnIdMap[$targetCol];
				} else {
					$ruleSet['targetCol'] = self::UNRESOLVED_ID;
					$ruleSet['broken'] = true;
				}
			}

			if (!isset($ruleSet['rules']) || !is_array($ruleSet['rules'])) {
				continue;
			}
			foreach ($ruleSet['rules'] as &$rule) {
				if (!is_array($rule)) {
					continue;
				}
				$rule['condition'] = self::remapConditionSet($rule['condition'] ?? [], $columnIdMap, $selectionOptionIdMap, $rule);
			}
			unset($rule);
		}
		unset($ruleSet);

		return $ruleSets;
	}

	/**
	 * @param array<int, int> $columnIdMap
	 * @param array<int, array<int, int>> $selectionOptionIdMap
	 */
	private static function remapConditionSet(array $conditionSet, array $columnIdMap, array $selectionOptionIdMap, array &$rule): array {
		if (!isset($conditionSet['groups']) || !is_array($conditionSet['groups'])) {
			return $conditionSet;
		}

		foreach ($conditionSet['groups'] as &$group) {
			if (!isset($group['conditions']) || !is_array($group['conditions'])) {
				continue;
			}
			foreach ($group['conditions'] as &$condition) {
				if (!is_array($condition)) {
					continue;
				}
				$oldColumnId = $condition['columnId'] ?? null;
				if ($oldColumnId !== null && !self::isMetaColumnId($oldColumnId)) {
					if (isset($columnIdMap[$oldColumnId])) {
						$condition['columnId'] = $columnIdMap[$oldColumnId];
					} else {
						$condition['columnId'] = self::UNRESOLVED_ID;
						$rule['broken'] = true;
					}
				}

				if (!isset($condition['value']) || !array_key_exists($oldColumnId, $selectionOptionIdMap)) {
					continue;
				}
				$optionMap = $selectionOptionIdMap[$oldColumnId];
				if (is_array($condition['value'])) {
					foreach ($condition['value'] as &$entry) {
						$entry = self::remapSelectionOption($entry, $optionMap, $rule);
					}
					unset($entry);
				} else {
					$condition['value'] = self::remapSelectionOption($condition['value'], $optionMap, $rule);
				}
			}
			unset($condition);
		}
		unset($group);

		return $conditionSet;
	}

	private static function isMetaColumnId(mixed $columnId): bool {
		return is_numeric($columnId) && Column::isValidMetaTypeId((int)$columnId);
	}

	/**
	 * @param mixed $value
	 * @param array<int, int> $optionIdMap the owning column's map, old option id => new option id
	 * @return mixed
	 */
	private static function remapSelectionOption($value, array $optionIdMap, array &$rule) {
		if (is_array($value) && isset($value['id'])) {
			$oldOptionId = (int)$value['id'];
			if (!isset($optionIdMap[$oldOptionId])) {
				$rule['broken'] = true;
				$value['id'] = self::UNRESOLVED_OPTION_ID;
				return $value;
			}
			$value['id'] = $optionIdMap[$oldOptionId];
			return $value;
		}

		$oldId = SelectionReference::optionId($value);
		if ($oldId === null) {
			return $value;
		}

		if (!isset($optionIdMap[$oldId])) {
			$rule['broken'] = true;
			return SelectionReference::forOptionId(self::UNRESOLVED_OPTION_ID);
		}

		return SelectionReference::forOptionId($optionIdMap[$oldId]);
	}
}
