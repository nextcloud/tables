/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { CARD_LAYOUTS, LAYOUT_TABLE } from '../constants.ts'

/**
 * The layout to render with.
 *
 * A viewer may switch layout for themselves; that choice lives in the local view
 * setting and is never saved, so it falls back to the layout of the view itself.
 * A value this version does not know renders as a table rather than as nothing.
 *
 * @param {object|null} viewSetting the local view setting of a table or view
 * @param {string|null} savedLayout the layout the view is stored with
 * @return {string} one of the known layouts
 */
export function resolveLayout(viewSetting, savedLayout) {
	const layout = viewSetting?.layout ?? savedLayout

	return CARD_LAYOUTS.includes(layout) ? layout : LAYOUT_TABLE
}

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
