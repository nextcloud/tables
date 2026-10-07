/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { translate as t } from '@nextcloud/l10n'
import HeaderWidget from './widgets/HeaderWidget.vue'
import TextWidget from './widgets/TextWidget.vue'
import DataWidget from './widgets/DataWidget.vue'

/** Number of columns of a grid view. */
export const GRID_COLUMNS = 12

/** Height of one grid row in pixels. */
export const GRID_CELL_HEIGHT = 80

export const WIDGET_TYPE_HEADER = 'header'
export const WIDGET_TYPE_TEXT = 'text'
export const WIDGET_TYPE_DATA = 'data'

/**
 * The widget types a grid view can hold. The content shape of each type is
 * what the add-widget dialog edits and what the widget component renders.
 */
export const widgetTypes = {
	[WIDGET_TYPE_HEADER]: {
		displayName: () => t('tables', 'Header'),
		component: HeaderWidget,
		defaultSize: { gridWidth: GRID_COLUMNS, gridHeight: 2 },
		showTitle: false,
		defaultContent: () => ({
			title: '',
			subtitle: '',
			backgroundColor: '',
			textColor: '',
			textAlign: 'left',
		}),
	},
	[WIDGET_TYPE_TEXT]: {
		displayName: () => t('tables', 'Description'),
		component: TextWidget,
		defaultSize: { gridWidth: 6, gridHeight: 2 },
		showTitle: true,
		defaultContent: () => ({
			text: '',
		}),
	},
	[WIDGET_TYPE_DATA]: {
		displayName: () => t('tables', 'Table or view'),
		component: DataWidget,
		defaultSize: { gridWidth: GRID_COLUMNS, gridHeight: 5 },
		showTitle: false,
		defaultContent: () => ({
			targetType: 'table',
			targetId: null,
		}),
	},
}

/**
 * @param {string} type widget type
 * @return {object|null}
 */
export function getWidgetType(type) {
	return widgetTypes[type] ?? null
}

/**
 * @return {Array<{id: string, label: string}>}
 */
export function listWidgetTypes() {
	return Object.entries(widgetTypes).map(([id, entry]) => ({ id, label: entry.displayName() }))
}

/**
 * An empty grid, the value of a view that was never designed.
 *
 * @return {{widgets: Array, layout: Array}}
 */
export function emptyGrid() {
	return { widgets: [], layout: [] }
}

/**
 * Deep copy of a grid so edits never touch the stored view until saved.
 *
 * @param {object} grid grid as stored on the view
 * @return {{widgets: Array, layout: Array}}
 */
export function cloneGrid(grid) {
	return {
		widgets: JSON.parse(JSON.stringify(grid?.widgets ?? [])),
		layout: JSON.parse(JSON.stringify(grid?.layout ?? [])),
	}
}

/**
 * The row below the lowest widget, where a new widget is placed.
 *
 * @param {Array} layout layout items
 * @return {number}
 */
export function nextFreeRow(layout) {
	return layout.reduce((max, item) => Math.max(max, (item.gridY ?? 0) + (item.gridHeight ?? 1)), 0)
}
