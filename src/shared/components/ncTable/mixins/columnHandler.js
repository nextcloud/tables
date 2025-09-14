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
