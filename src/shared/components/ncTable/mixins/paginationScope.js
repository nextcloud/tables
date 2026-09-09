/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const PAGINATION_CHANGED = 'tables:pagination-changed'

/**
 * Whether a pagination event was raised by controls of the same table or view.
 *
 * Several tables can be on screen at once, so a control only follows an event that
 * names its own node.
 *
 * @param {object} target the receiving node, holding elementId and isView
 * @param {object} payload the event payload, holding elementId and isView
 * @return {boolean} true when both name the same node
 */
export function isSamePaginationTarget(target, payload) {
	if (target.isView !== payload.isView) {
		return false
	}

	return normalizeElementId(target.elementId) === normalizeElementId(payload.elementId)
}

/**
 * Every component that raises or receives the event declares elementId as a Number, so
 * only the absent case needs normalising.
 *
 * @param {number|null} elementId the id as a prop or an event payload carries it
 * @return {number|null} the id, or null when there is none
 */
function normalizeElementId(elementId) {
	return elementId ?? null
}
