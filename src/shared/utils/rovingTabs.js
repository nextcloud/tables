/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * The tab a key moves the focus to in a tab list with one tab stop: the arrow keys move to
 * the previous or next tab and wrap, Home and End to the first or last one. A key with Alt,
 * Ctrl or Meta belongs to the browser or the system, so it moves nothing.
 *
 * @param {KeyboardEvent} event the key that was pressed in the tab list
 * @param {number} current position of the tab the key was pressed on
 * @param {number} count number of tabs
 * @param {boolean} isRtl whether the tab list runs right to left
 * @return {number|null} position of the tab to focus, or null when the key moves nothing
 */
export function rovingTabTarget(event, current, count, isRtl) {
	if (event.altKey || event.ctrlKey || event.metaKey || count === 0) {
		return null
	}
	const step = { ArrowRight: isRtl ? -1 : 1, ArrowLeft: isRtl ? 1 : -1 }
	if (event.key in step) {
		return (Math.max(0, current) + step[event.key] + count) % count
	}
	if (event.key === 'Home') {
		return 0
	}
	if (event.key === 'End') {
		return count - 1
	}
	return null
}

/**
 * @param {KeyboardEvent} event a key pressed in a tab list, which handles it
 * @return {number} position of the tab the key was pressed on, or -1 outside a tab
 */
export function eventTabIndex(event) {
	const tabs = [...event.currentTarget.querySelectorAll('[role="tab"]')]
	return tabs.indexOf(event.target.closest('[role="tab"]'))
}
