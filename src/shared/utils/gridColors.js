/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Only hex colors are accepted in widget content, the same rule the backend applies.
 *
 * @param {string|null|undefined} value a color as entered by the user
 * @return {boolean}
 */
export function isHexColor(value) {
	return typeof value === 'string' && /^#[0-9a-fA-F]{3,6}$/.test(value)
}
