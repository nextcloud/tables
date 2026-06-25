/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * @param {string|null|undefined} technicalName
 * @return {string|null}
 */
export function normalizeTechnicalName(technicalName) {
	const normalized = technicalName?.trim()
	return normalized === '' ? null : normalized
}

/**
 * returns true if the technical name is valid including null value.
 *
 * @param {string|null|undefined} technicalName
 * @return {boolean}
 */
export function isTechnicalNameValid(technicalName) {
	const normalized = normalizeTechnicalName(technicalName)
	if (normalized === null) {
		return true
	}
	return /^[a-z][a-z0-9_]*$/.test(normalized)
}
