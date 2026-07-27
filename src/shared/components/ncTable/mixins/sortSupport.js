/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	TYPE_META_CREATED_AT,
	TYPE_META_CREATED_BY,
	TYPE_META_ID,
	TYPE_META_UPDATED_AT,
	TYPE_META_UPDATED_BY,
	TYPE_DATETIME,
	TYPE_DATETIME_DATE,
	TYPE_DATETIME_TIME,
	TYPE_NUMBER,
	TYPE_NUMBER_PROGRESS,
	TYPE_NUMBER_STARS,
	TYPE_TEXT_LINK,
	TYPE_SELECTION,
	TYPE_SELECTION_MULTI,
	TYPE_TEXT_LINE,
} from '../../../constants.ts'

export const BACKEND_SORTABLE_TYPES = new Set([
	TYPE_TEXT_LINE,
	TYPE_TEXT_LINK,
	TYPE_SELECTION,
	TYPE_SELECTION_MULTI,
	TYPE_NUMBER,
	TYPE_NUMBER_STARS,
	TYPE_NUMBER_PROGRESS,
	TYPE_DATETIME_DATE,
	TYPE_DATETIME_TIME,
	TYPE_DATETIME,
])

export function getBackendSortableColumnType(columnId, columnType = null) {
	// Meta columns reuse backend scalar sort behavior based on their stored row fields.
	if (columnId === TYPE_META_ID) {
		return TYPE_NUMBER
	}

	if (columnId === TYPE_META_CREATED_BY || columnId === TYPE_META_UPDATED_BY) {
		return TYPE_TEXT_LINE
	}

	if (columnId === TYPE_META_CREATED_AT || columnId === TYPE_META_UPDATED_AT) {
		return TYPE_DATETIME
	}

	return columnType
}

export function isBackendSortableColumn({ id, type }) {
	if (id < 0) {
		return true
	}

	return BACKEND_SORTABLE_TYPES.has(getBackendSortableColumnType(id, type))
}

export function getColumnForSort(columns = [], columnId) {
	return (columns || []).find(col => col.id === columnId) ?? { id: columnId, type: null }
}

export function isBackendSortableRule(rule, columns = []) {
	const normalizedRule = normalizeBackendSortRules([rule])[0]
	if (!normalizedRule) {
		return false
	}

	return isBackendSortableColumn(getColumnForSort(columns, normalizedRule.columnId))
}

export function normalizeBackendSortRules(sortRules = []) {
	if (!Array.isArray(sortRules)) {
		return []
	}

	// Keep the request contract narrow so unsupported UI state never reaches the backend.
	return sortRules
		.map(rule => ({
			columnId: Number.parseInt(rule?.columnId, 10),
			mode: rule?.mode === 'DESC' ? 'DESC' : 'ASC',
		}))
		.filter(rule => Number.isInteger(rule.columnId))
}

export function normalizeBackendSortRulesForColumns(sortRules = [], columns = []) {
	return normalizeBackendSortRules(sortRules)
		.filter(rule => isBackendSortableColumn(getColumnForSort(columns, rule.columnId)))
}

export function getExplicitSortRules(viewSetting = {}) {
	if (Array.isArray(viewSetting?.sorting) && viewSetting.sorting.length > 0) {
		return viewSetting.sorting
	}

	return []
}
