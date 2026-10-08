/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
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
 * The component that renders each widget type. Everything else about a type,
 * its title, default size and the schema of its content, comes from the server
 * (see GridWidgetTypes.php) so the form and the validation share one definition.
 */
const widgetComponents = {
	[WIDGET_TYPE_HEADER]: HeaderWidget,
	[WIDGET_TYPE_TEXT]: TextWidget,
	[WIDGET_TYPE_DATA]: DataWidget,
}

/**
 * @param {string} type widget type
 * @return {object|null} the component that renders it
 */
export function getWidgetComponent(type) {
	return widgetComponents[type] ?? null
}

/**
 * @param {Array} widgetTypes the schemas loaded from the server
 * @param {string} type widget type
 * @return {object|null}
 */
export function findWidgetType(widgetTypes, type) {
	return widgetTypes.find(widgetType => widgetType.type === type) ?? null
}

/**
 * The configuration a new widget of this type starts with: every property at its default.
 *
 * @param {object} widgetType a schema from the server
 * @return {object}
 */
export function defaultConfiguration(widgetType) {
	return Object.fromEntries(Object.entries(widgetType?.configuration ?? {}).map(([name, property]) => [name, structuredClone(property.default ?? null)]))
}

/**
 * The content a new widget of this type starts with: every property at its default.
 *
 * @param {object} widgetType a schema from the server
 * @return {object}
 */
export function defaultContent(widgetType) {
	return Object.fromEntries(Object.entries(widgetType?.properties ?? {}).map(([name, property]) => [name, structuredClone(property.default ?? null)]))
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
