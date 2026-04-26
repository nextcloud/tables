/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * @param {object} conditionSet condition set as held by an editor
 * @return {object} a condition set holding only complete conditions
 */
export function cleanConditionSet(conditionSet) {
	const groups = (conditionSet?.groups ?? [])
		.map(group => ({
			...group,
			conditions: (group.conditions ?? []).filter(
				condition => condition.columnId != null && condition.operator != null && condition.columnType,
			),
		}))
		.filter(group => group.conditions.length > 0)
	return { groups }
}
