<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Tables\Model;

/**
 * Translation of stored formatting rule sets onto a freshly imported table.
 *
 * Lives here rather than in either importer so that the user-migration path and the
 * scheme path remap the same way, in the same place, the way ViewSettings::remapSources()
 * already does for card sources.
 */
class FormattingRuleSets {
	/** Prefix of a stored reference to a selection option, see ColumnService::importColumn(). */
	public const SELECTION_VALUE_PREFIX = '@selection-id-';

	/**
	 * Point every column and selection-option reference at its imported counterpart.
	 *
	 * A reference that does not survive the import leaves the rule flagged `broken` rather
	 * than dropped, because a formatting rule selects which values are painted; the id is
	 * kept as-is so the editor can still show the user what the rule used to read.
	 *
	 * An option id is only unique within the column that owns it, so $selectionOptionIdMap is
	 * kept per column rather than flattened into one lookup.
	 *
	 * @param array $ruleSets
	 * @param array<int, int> $columnIdMap old column id => new column id
	 * @param array<int, array<int, int>> $selectionOptionIdMap old column id => [old option id => new option id]
	 */
	public static function remapIds(array $ruleSets, array $columnIdMap, array $selectionOptionIdMap): array {
		foreach ($ruleSets as &$ruleSet) {
			if (($ruleSet['targetCol'] ?? null) !== null) {
				if (isset($columnIdMap[$ruleSet['targetCol']])) {
					$ruleSet['targetCol'] = $columnIdMap[$ruleSet['targetCol']];
				} else {
					$ruleSet['broken'] = true;
				}
			}

			if (!isset($ruleSet['rules']) || !is_array($ruleSet['rules'])) {
				continue;
			}
			// Iterating `$ruleSet['rules'] ?? []` would bind the reference to a temporary and
			// silently discard every write, so the array is checked first and iterated directly.
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
				// Captured before the id is rewritten: an option reference in this condition's
				// value belongs to the column the condition reads, so it has to be resolved
				// against that column's own option map.
				$oldColumnId = $condition['columnId'] ?? null;
				if (isset($columnIdMap[$oldColumnId])) {
					$condition['columnId'] = $columnIdMap[$oldColumnId];
				} else {
					$rule['broken'] = true;
				}

				if (!isset($condition['value'])) {
					continue;
				}
				$optionMap = $selectionOptionIdMap[$oldColumnId] ?? [];
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

	/**
	 * Point a `@selection-id-<id>` reference at the imported option, flagging the rule broken
	 * when the option did not survive the import. Other values are returned unchanged.
	 *
	 * @param mixed $value
	 * @param array<int, int> $optionIdMap the owning column's map, old option id => new option id
	 * @return mixed
	 */
	private static function remapSelectionOption($value, array $optionIdMap, array &$rule) {
		// A contains-item condition stores whole option objects instead of the magic string,
		// so the id is remapped in place and the rest of the object is left alone.
		if (is_array($value) && isset($value['id'])) {
			$oldOptionId = (int)$value['id'];
			if (!isset($optionIdMap[$oldOptionId])) {
				$rule['broken'] = true;
				return $value;
			}
			$value['id'] = $optionIdMap[$oldOptionId];
			return $value;
		}

		if (!is_string($value) || !str_starts_with($value, self::SELECTION_VALUE_PREFIX)) {
			return $value;
		}

		$oldId = (int)substr($value, strlen(self::SELECTION_VALUE_PREFIX));
		if (!isset($optionIdMap[$oldId])) {
			$rule['broken'] = true;
			return $value;
		}

		return self::SELECTION_VALUE_PREFIX . $optionIdMap[$oldId];
	}
}
