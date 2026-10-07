/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getCurrentUser } from '@nextcloud/auth'

/** Number of columns of the application grid on a wide screen. */
export const GRID_COLUMNS = 12

/** Number of columns the grid collapses to on a medium screen. */
export const GRID_COLUMNS_MEDIUM = 6

/** Column spans a table or view can take in the application grid. */
export const GRID_SPANS = [12, 8, 6, 4]

/**
 * @param {number|string} contextId id of the application
 * @return {string}
 */
function getStorageKey(contextId) {
	return 'tables:context-layout:' + (getCurrentUser()?.uid ?? '') + ':' + contextId
}

/**
 * Inline style placing one grid item, read by the grid's responsive rules.
 *
 * @param {number} span number of columns the item spans on a wide screen
 * @return {object}
 */
export function getGridCellStyle(span) {
	return {
		'--context-grid-span': span,
		'--context-grid-span-medium': Math.min(span, GRID_COLUMNS_MEDIUM),
	}
}

/**
 * The current user's arrangement of an application, as a map of resource key to column span.
 * Browser storage can be unavailable (private windows, blocked site data); the default layout is used then.
 *
 * @param {number|string} contextId id of the application
 * @return {Object<string, number>}
 */
export function loadContextLayout(contextId) {
	try {
		const storedLayout = JSON.parse(window.localStorage.getItem(getStorageKey(contextId)) ?? '{}')
		return Object.fromEntries(Object.entries(storedLayout ?? {})
			.filter(([, span]) => GRID_SPANS.includes(span)))
	} catch (error) {
		console.debug('Application layout could not be read, using the default', error)
		return {}
	}
}

/**
 * @param {number|string} contextId id of the application
 * @param {Object<string, number>} layout map of resource key to column span; an empty map resets to the default
 */
export function saveContextLayout(contextId, layout) {
	try {
		if (Object.keys(layout).length === 0) {
			window.localStorage.removeItem(getStorageKey(contextId))
			return
		}
		window.localStorage.setItem(getStorageKey(contextId), JSON.stringify(layout))
	} catch (error) {
		console.debug('Application layout could not be stored, it applies to this visit only', error)
	}
}
