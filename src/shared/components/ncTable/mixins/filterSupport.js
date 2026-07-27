/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Backend filter query support
 */
import { TYPE_SELECTION } from '../../../constants.ts'
import { FilterIds } from './filter.js'

// Return a column object for a given id. For meta columns, which are not in
// `this.columns`, return a small fallback object.
export function getColumnForFilter(columns = [], columnId) {
	return (columns || []).find(col => col.id === columnId) ?? { id: columnId, type: null }
}

export function parseBackendFilterGroups(customFiltersString) {
	if (!customFiltersString) {
		return []
	}

	try {
		const parsed = JSON.parse(customFiltersString)
		if (!Array.isArray(parsed)) {
			return []
		}

		// URL query data can be invalid, so keep only filter groups and rules that
		// match the backend format we expect.
		return parsed
			.filter(group => Array.isArray(group))
			.map(group => group
				.filter(rule => rule && typeof rule === 'object')
				.filter(rule => Number.isInteger(Number.parseInt(rule.columnId, 10)) && typeof rule.operator === 'string')
				.map(rule => ({
					columnId: Number.parseInt(rule.columnId, 10),
					operator: rule.operator,
					value: rule.value ?? '',
				})))
			.filter(group => group.length > 0)
	} catch (error) {
		return []
	}
}
// Convert filters currently stored in the page UI into backend filter groups.
export function normalizeRuntimeFilterGroups(runtimeFilters = [], columns = []) {
	if (!Array.isArray(runtimeFilters) || runtimeFilters.length === 0) {
		return []
	}

	// The UI stores filters as one flat list. The backend expects groups, where
	// every rule inside one group must match.
	let groups = [[]]

	for (const filter of runtimeFilters) {
		if (!filter || typeof filter !== 'object') {
			continue
		}

		const columnId = Number.parseInt(filter.columnId, 10)
		if (!Number.isInteger(columnId)) {
			continue
		}

		const operatorId = filter?.operator?.id
		if (!operatorId) {
			continue
		}

		if (operatorId === FilterIds.ContainsItem) {
			const selectedOptions = Array.isArray(filter.value) ? filter.value : []
			if (selectedOptions.length === 0) {
				continue
			}

			const columnType = getColumnForFilter(columns, columnId)?.type
			// A "contains item" filter can match several selected values, so expand
			// it into several backend rules and keep them as alternatives.
			const translatedRules = selectedOptions
				.map(option => option?.id)
				.filter(optionId => optionId !== null && optionId !== undefined)
				.map(optionId => ({
					columnId,
					operator: columnType === TYPE_SELECTION ? FilterIds.IsEqual : FilterIds.Contains,
					value: optionId,
				}))

			if (translatedRules.length === 0) {
				continue
			}

			// Normal filters must all match together, so add the rule to every group.
			groups = groups.flatMap(group => translatedRules.map(rule => [...group, rule]))
			continue
		}

		const normalizedRule = {
			columnId,
			operator: operatorId,
			value: filter.value ?? '',
		}
		groups = groups.map(group => [...group, normalizedRule])
	}

	return groups.filter(group => group.length > 0)
}

// This is used to merge URL filters with filters added in the current UI.
export function mergeBackendFilterGroups(leftGroups, rightGroups) {
	if (leftGroups.length === 0) {
		return rightGroups
	}

	if (rightGroups.length === 0) {
		return leftGroups
	}

	// Keep both sets of groups active at the same time by combining every left
	// group with every right group.
	return leftGroups.flatMap(leftGroup => rightGroups.map(rightGroup => [...leftGroup, ...rightGroup]))
}

export function buildBackendFilterQuery(customFiltersString, viewSetting = {}, columns = []) {
	const routeCustomFilters = parseBackendFilterGroups(customFiltersString)
	const runtimeFilterGroups = normalizeRuntimeFilterGroups(viewSetting?.filter, columns)
	const mergedCustomFilters = mergeBackendFilterGroups(routeCustomFilters, runtimeFilterGroups)

	return mergedCustomFilters.length > 0 ? JSON.stringify(mergedCustomFilters) : null
}
