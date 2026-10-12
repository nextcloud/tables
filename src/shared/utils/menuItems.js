/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * The payload the API expects for one menu item.
 *
 * @param {object} item an item from the editor
 * @param {Array} items the whole editable list, to turn the parent key into the parent's position
 * @return {object}
 */
export function toMenuItemPayload(item, items) {
	const parentIndex = item.parentKey ? items.findIndex(candidate => candidate.key === item.parentKey) : -1
	return {
		label: item.label,
		icon: item.icon || null,
		targetType: item.targetType,
		targetId: item.targetType === 'url' ? null : item.targetId,
		url: item.targetType === 'url' ? item.url : null,
		technicalName: item.technicalName || null,
		parentIndex: parentIndex >= 0 ? parentIndex : null,
	}
}
