/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export const SELECTION_ID_PREFIX = '@selection-id-'

/**
 * @param {string|number|boolean|Array|null} value a stored filter or condition value
 * @return {boolean} whether it is a selection option reference
 */
export function isSelectionReference(value) {
	return typeof value === 'string' && value.startsWith(SELECTION_ID_PREFIX)
}

/**
 * @param {string|number|boolean|Array|null} value a stored filter or condition value
 * @return {number|null} the referenced option id, or null when this is not a reference
 */
export function selectionOptionId(value) {
	if (!isSelectionReference(value)) {
		return null
	}
	const id = parseInt(value.slice(SELECTION_ID_PREFIX.length), 10)
	return Number.isNaN(id) ? null : id
}

/**
 * @param {number|string} optionId a selection option id
 * @return {string} the reference to store for it
 */
export function selectionReferenceFor(optionId) {
	return SELECTION_ID_PREFIX + optionId
}

/**
 * @param {string|number|boolean|Array|null} value a stored filter or condition value
 * @param {object} column the column the value belongs to
 * @return {string|number|boolean|Array|null} the option's label, or the value unchanged when it is not a resolvable reference
 */
export function selectionOptionLabel(value, column) {
	const optionId = selectionOptionId(value)
	if (optionId === null) {
		return value
	}
	const option = column?.selectionOptions?.find(item => Number(item.id) === optionId)
	return option?.label ?? value
}
