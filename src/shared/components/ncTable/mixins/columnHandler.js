/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
export const ColumnTypes = {
	TextLine: 'text-line',
	TextLong: 'text-long',
	TextRich: 'text-rich',
	Selection: 'selection',
	SelectionMulti: 'selection-multi',
	Number: 'number',
	SelectionCheck: 'selection-check',
	TextLink: 'text-link',
	NumberStars: 'number-stars',
	NumberProgress: 'number-progress',
	DatetimeDate: 'datetime-date',
	DatetimeTime: 'datetime-time',
	Datetime: 'datetime',
	Usergroup: 'usergroup',
}

export function getColumnWidthStyle(column) {
	const width = column.customSettings?.width ? `min(${column.customSettings.width}px, 90vw)` : null

	return width ? { width, maxWidth: width, minWidth: width } : null
}

const CHECKBOX_COLUMN_WIDTH = 60
const DEFAULT_COLUMN_WIDTH = 150

/**
 * Returns a sticky-positioning style for a column within the frozen range, or null if it should scroll normally.
 *
 * columnWidths is an optional map of col.id → measured offsetWidth (px). When supplied it is used for the
 * left-offset calculation so the offsets match the browser's actual auto-layout widths. Without it the
 * calculation falls back to customSettings.width or DEFAULT_COLUMN_WIDTH.
 *
 * No explicit width is set on the frozen cell itself — the table's auto-layout already maintains the
 * correct column width across all rows. Forcing a width here caused auto-sized columns to be narrowed
 * to the DEFAULT_COLUMN_WIDTH fallback.
 *
 * Hidden columns are excluded from visibleColumns before this function is called, so pinnedColumnIndex
 * naturally resolves to -1 when the pinned column is hidden, causing the freeze to silently disappear.
 * This is intentional: when the pinned column is re-shown the freeze resumes automatically.
 */
export function getFrozenColumnStyle(col, colIndex, pinnedColumnIndex, hasCheckboxColumn, visibleColumns, columnWidths = null) {
	if (pinnedColumnIndex < 0 || colIndex > pinnedColumnIndex) {
		return null
	}

	const getWidth = (c) => columnWidths?.[c.id] ?? c.customSettings?.width ?? DEFAULT_COLUMN_WIDTH
	const baseOffset = hasCheckboxColumn ? CHECKBOX_COLUMN_WIDTH : 0
	const leftOffset = visibleColumns
		.slice(0, colIndex)
		.reduce((sum, c) => sum + getWidth(c), baseOffset)

	return {
		position: 'sticky',
		insetInlineStart: `${leftOffset}px`,
		zIndex: 4,
		backgroundColor: 'inherit',
	}
}
