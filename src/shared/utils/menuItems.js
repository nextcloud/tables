/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Menu items as the API stores them, with a stable key for list rendering while editing.
 *
 * @param {Array|undefined|null} menuItems items from the application
 * @return {Array}
 */
export function toEditableMenuItems(menuItems) {
	return (menuItems ?? []).map((item, index) => ({
		key: 'item-' + (item.id ?? index),
		label: item.label ?? '',
		icon: item.icon ?? null,
		targetType: item.targetType ?? 'url',
		targetId: item.targetId ?? null,
		url: item.url ?? '',
		slug: item.slug ?? null,
	}))
}

/**
 * The payload the API expects for one menu item.
 *
 * @param {object} item an item from the editor
 * @return {object}
 */
export function toMenuItemPayload(item) {
	return {
		label: item.label,
		icon: item.icon || null,
		targetType: item.targetType,
		targetId: item.targetType === 'url' ? null : item.targetId,
		url: item.targetType === 'url' ? item.url : null,
		slug: item.slug || null,
	}
}

/**
 * Where a menu item leads inside the app, or null for an external link.
 *
 * @param {object} item a menu item
 * @param {number|string} contextId the application the item belongs to
 * @return {string|null}
 */
export function menuItemRoute(item, contextId) {
	if (item.targetType === 'url') {
		return null
	}
	return '/application/' + contextId + '/menu/' + item.slug
}
