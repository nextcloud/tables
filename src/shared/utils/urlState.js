/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getFilterWithId } from '../components/ncTable/mixins/filter.js'

const QUERY_KEYS = {
	filter: 'filter',
	sort: 'sort',
	search: 'search',
	page: 'page',
	perPage: 'perPage',
}

const DEFAULT_ROWS_PER_PAGE = 100

/**
 * Build a URL query object from the current table view state.
 *
 * @param {object} viewSetting The view setting object.
 * @param {number} pageNumber The current page number.
 * @param {number} rowsPerPage The current rows per page.
 * @returns {object} A query object to be used with Vue Router.
 */
function serializeFilterRule(rule) {
	return {
		columnId: rule?.columnId,
		operator: rule?.operator?.id ?? rule?.operator,
		value: rule?.value,
	}
}

function serializeSortRule(rule) {
	return {
		columnId: rule?.columnId,
		mode: rule?.mode,
	}
}

export function buildUrlQuery(viewSetting, pageNumber, rowsPerPage) {
	const query = {}

	if (viewSetting?.filter?.length) {
		query[QUERY_KEYS.filter] = JSON.stringify(viewSetting.filter.map(serializeFilterRule))
	}

	if (viewSetting?.sorting?.length) {
		query[QUERY_KEYS.sort] = JSON.stringify(viewSetting.sorting.map(serializeSortRule))
	}

	if (viewSetting?.searchString) {
		query[QUERY_KEYS.search] = viewSetting.searchString
	}

	if (pageNumber && pageNumber > 1) {
		query[QUERY_KEYS.page] = String(pageNumber)
	}

	if (rowsPerPage && rowsPerPage !== DEFAULT_ROWS_PER_PAGE) {
		query[QUERY_KEYS.perPage] = String(rowsPerPage)
	}

	return query
}

/**
 * Parse the current URL query into a view state object.
 *
 * @param {object} query The Vue Router query object.
 * @returns {object} The parsed state.
 */
export function parseUrlQuery(query) {
	const state = {
		filter: [],
		sorting: [],
		searchString: null,
		pageNumber: 1,
		rowsPerPage: DEFAULT_ROWS_PER_PAGE,
	}

	if (query[QUERY_KEYS.filter]) {
		try {
			const parsed = JSON.parse(query[QUERY_KEYS.filter])
			state.filter = Array.isArray(parsed)
				? parsed.map(rule => ({
					...rule,
					operator: typeof rule?.operator === 'string'
						? (getFilterWithId(rule.operator) ?? rule.operator)
						: rule?.operator,
				}))
				: []
		} catch (e) {
			state.filter = []
		}
	}

	if (query[QUERY_KEYS.sort]) {
		try {
			const parsed = JSON.parse(query[QUERY_KEYS.sort])
			state.sorting = Array.isArray(parsed) ? parsed : []
		} catch (e) {
			state.sorting = []
		}
	}

	if (query[QUERY_KEYS.search]) {
		state.searchString = String(query[QUERY_KEYS.search])
	}

	if (query[QUERY_KEYS.page]) {
		const parsed = parseInt(query[QUERY_KEYS.page], 10)
		state.pageNumber = isNaN(parsed) || parsed < 1 ? 1 : parsed
	}

	if (query[QUERY_KEYS.perPage]) {
		const parsed = parseInt(query[QUERY_KEYS.perPage], 10)
		state.rowsPerPage = isNaN(parsed) || parsed < 1 ? DEFAULT_ROWS_PER_PAGE : parsed
	}

	return state
}
