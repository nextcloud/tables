/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ColumnTypes } from '../components/ncTable/mixins/columnHandler.js'

/**
 * Whether the column is virtual, i.e. it does not store its own cell data.
 * For plain column objects only — AbstractColumn instances provide isVirtual().
 *
 * @param {object|null|undefined} column the column to check
 * @return {boolean}
 */
export function isVirtualColumn(column) {
	return column?.type === ColumnTypes.Lookup
}

/**
 * @param {string|null|undefined} technicalName the raw technical name to normalize
 * @return {string|null}
 */
export function normalizeTechnicalName(technicalName) {
	const normalized = technicalName?.trim()
	return normalized === '' ? null : normalized
}

/**
 * returns true if the technical name is valid including null value.
 *
 * @param {string|null|undefined} technicalName the technical name to validate
 * @return {boolean}
 */
export function isTechnicalNameValid(technicalName) {
	const normalized = normalizeTechnicalName(technicalName)
	if (normalized === null) {
		return true
	}
	return /^[a-z][a-z0-9_]*$/.test(normalized)
}
