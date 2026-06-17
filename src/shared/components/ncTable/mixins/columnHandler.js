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
	Relation: 'relation',
}

export function getColumnWidthStyle(column) {
	const width = column.customSettings?.width ? `min(${column.customSettings.width}px, 90vw)` : null

	return width ? { width, maxWidth: width, minWidth: width } : null
}

const CHECKBOX_COLUMN_WIDTH = 60
const DEFAULT_COLUMN_WIDTH = 150

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
