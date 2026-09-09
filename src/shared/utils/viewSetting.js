/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Whether the viewer has adjusted the view for themselves.
 *
 * These are the unsaved tweaks that "Reset local adjustments" clears, the layout a
 * viewer switched to included. Several components show a cue for this state, so they
 * all have to agree on what counts.
 *
 * @param {object|null} viewSetting the local view setting of a table or view
 * @return {boolean} true when anything is set
 */
export function hasLocalViewAdjustments(viewSetting) {
	if (!viewSetting) {
		return false
	}

	return viewSetting.hiddenColumns?.length > 0
		|| !!viewSetting.sorting
		|| viewSetting.filter?.length > 0
		|| !!viewSetting.layout
}
