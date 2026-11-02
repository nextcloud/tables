/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../shared/constants.ts'

/** The page type of a grid page, as the API returns it. */
export const PAGE_TYPE_GRID = 'grid'

/** The page type of the start page of an application. */
export const PAGE_TYPE_START = 'startpage'

/** Technical names of pages: lower case letters, digits and underscores, starting with a letter. */
export const PAGE_TECHNICAL_NAME_PATTERN = /^[a-z][a-z0-9_]*$/

/** Names of this form are kept for the names the server gives pages itself. */
export const PAGE_TECHNICAL_NAME_RESERVED = /^page_\d+$/

/** Number of columns of a grid page. */
export const GRID_COLUMNS = 12

/** Smallest number of columns a widget spans. */
export const GRID_MIN_WIDTH = 2

/** Height of one grid row. In pixels on purpose: GridStack takes a number, not a CSS variable. */
export const GRID_CELL_HEIGHT = 80

/** Space between widgets in pixels: two grid baselines of 4px. */
export const GRID_MARGIN = 8

/** Below this canvas width in pixels the grid shows one column. */
export const GRID_BREAKPOINT_SINGLE = 560

/** Below this canvas width in pixels the grid shows six columns. */
export const GRID_BREAKPOINT_MEDIUM = 960

/**
 * Deep copy of plain data. A JSON round-trip also copies reactive proxies, which
 * structuredClone refuses.
 *
 * @param {object|Array|string|number|boolean|null|undefined} value plain data
 * @return {object|Array|string|number|boolean|null} the copy
 */
function copy(value) {
	return JSON.parse(JSON.stringify(value ?? null))
}

/**
 * @param {Array} widgetTypes the types loaded from the server
 * @param {string} type widget type
 * @return {object|null} that type, or null when the server does not know it
 */
export function findWidgetType(widgetTypes, type) {
	return widgetTypes.find(widgetType => widgetType.type === type) ?? null
}

/**
 * The settings a new widget of this type starts with: every setting at its default.
 *
 * @param {object|null} widgetType a type from the server
 * @return {object} the settings
 */
export function defaultSettings(widgetType) {
	return Object.fromEntries(Object.entries(widgetType?.settings ?? {}).map(([name, setting]) => [name, copy(setting.default)]))
}

/**
 * Deep copy of a grid so edits never touch the stored page until saved.
 *
 * @param {object} grid grid as built by pageToGrid
 * @return {{widgets: Array, layout: Array}} the copy
 */
export function cloneGrid(grid) {
	return {
		widgets: copy(grid?.widgets ?? []),
		layout: copy(grid?.layout ?? []),
	}
}

/**
 * The row below the lowest widget, where a new widget is placed.
 *
 * @param {Array} layout layout items
 * @return {number} the row
 */
export function nextFreeRow(layout) {
	return layout.reduce((max, item) => Math.max(max, (item.gridY ?? 0) + (item.gridHeight ?? 1)), 0)
}

/**
 * How many columns the grid shows on a canvas of this width: 12 on a wide one, 6 on a
 * medium one and one on a phone.
 *
 * @param {number} width width of the canvas in pixels
 * @return {number} the number of columns
 */
export function columnsForWidth(width) {
	if (width < GRID_BREAKPOINT_SINGLE) {
		return 1
	}
	return width < GRID_BREAKPOINT_MEDIUM ? 6 : GRID_COLUMNS
}

/**
 * The layout with the positions of the GridStack nodes that changed applied to it.
 *
 * @param {Array} layout layout items
 * @param {Array<{id: string, x: number, y: number, w: number, h: number}>} nodes the changed nodes
 * @return {Array} the updated layout
 */
export function applyNodePositions(layout, nodes) {
	const positions = Object.fromEntries(nodes.map(node => [node.id, node]))
	return layout.map(item => {
		const node = positions[item.widgetId]
		return node ? { ...item, gridX: node.x, gridY: node.y, gridWidth: node.w, gridHeight: node.h } : item
	})
}

/**
 * The grid of a page: its widgets and where they sit. Widgets are keyed by `key`, which
 * is their id for saved widgets; a data widget keeps the node relation of its source.
 *
 * @param {object} page a page as the API returns it
 * @return {{widgets: Array, layout: Array}} the grid
 */
export function pageToGrid(page) {
	const widgets = page?.widgets ?? []
	return {
		widgets: widgets.map(widget => ({
			key: 'w' + widget.id,
			id: widget.id,
			type: widget.type,
			title: widget.title ?? '',
			icon: widget.icon ?? null,
			description: widget.description ?? '',
			// An empty settings map arrives as a JSON array
			settings: Array.isArray(widget.settings) ? {} : copy(widget.settings ?? {}),
			nodeRelId: widget.source?.nodeRelId ?? null,
			parentKey: widget.parentId ? 'w' + widget.parentId : null,
			parentTab: widget.parentTab ?? null,
		})),
		layout: widgets.map(widget => ({
			widgetId: 'w' + widget.id,
			gridX: widget.gridX,
			gridY: widget.gridY,
			gridWidth: widget.gridWidth,
			gridHeight: widget.gridHeight,
		})),
	}
}

/**
 * The table or view a data widget shows.
 *
 * @param {object} widget a widget of the grid
 * @param {object} nodes the nodes of the application by relation id, as in context.nodes
 * @return {{type: string, id: number}|null} the target, or null when the widget has no source
 * or its table or view left the application
 */
export function widgetTarget(widget, nodes) {
	const node = widget?.nodeRelId ? nodes?.[widget.nodeRelId] : null
	if (!node) {
		return null
	}
	const nodeType = parseInt(node.node_type)
	if (nodeType !== NODE_TYPE_TABLE && nodeType !== NODE_TYPE_VIEW) {
		return null
	}
	return { type: nodeType === NODE_TYPE_VIEW ? 'view' : 'table', id: parseInt(node.node_id) }
}

/**
 * The tables and views of the application a data widget can show, with their titles.
 *
 * @param {object} nodes the nodes of the application by relation id
 * @param {Array} tables tables known to the store
 * @param {Array} views views known to the store
 * @return {Array<{id: number, label: string}>} one option per node relation, by relation id
 */
export function applicationNodeOptions(nodes, tables, views) {
	return Object.values(nodes ?? {}).map(node => {
		const nodeType = parseInt(node.node_type)
		const nodeId = parseInt(node.node_id)
		const element = nodeType === NODE_TYPE_VIEW
			? views.find(view => view.id === nodeId)
			: tables.find(table => table.id === nodeId)
		const title = element ? (element.emoji ? element.emoji + ' ' : '') + element.title : '#' + nodeId
		return {
			id: parseInt(node.id),
			label: nodeType === NODE_TYPE_VIEW
				? t('tables', '{title} (view)', { title }, undefined, { escape: false })
				: title,
		}
	})
}

/**
 * The widgets of a grid as the API saves them. A saved widget keeps its id; a widget of
 * a type with a source names the node relation it shows, or null.
 *
 * @param {{widgets: Array, layout: Array}} grid the grid
 * @param {Array} widgetTypes the types loaded from the server
 * @return {Array} the widgets to save
 */
export function gridToWidgets(grid, widgetTypes) {
	const positions = Object.fromEntries(grid.layout.map(item => [item.widgetId, item]))
	const placed = grid.widgets.filter(widget => positions[widget.key])
	const indexOfKey = Object.fromEntries(placed.map((widget, index) => [widget.key, index]))
	return placed.map(widget => {
		const { gridX, gridY, gridWidth, gridHeight } = positions[widget.key]
		const saved = {
			type: widget.type,
			title: widget.title?.trim() || null,
			icon: widget.icon || null,
			description: widget.description?.trim() || null,
			gridX,
			gridY,
			gridWidth,
			gridHeight,
			settings: copy(widget.settings ?? {}),
		}
		if (widget.id) {
			saved.id = widget.id
		}
		if (findWidgetType(widgetTypes, widget.type)?.hasSource) {
			saved.source = widget.nodeRelId ? { nodeRelId: widget.nodeRelId } : null
		}
		if (widget.parentKey && indexOfKey[widget.parentKey] !== undefined) {
			saved.parentIndex = indexOfKey[widget.parentKey]
			saved.parentTab = widget.parentTab
		}
		return saved
	})
}

/**
 * The titles of the tabs of a tabs widget: the non-empty lines of its tabs setting, as on the server.
 *
 * @param {object} settings the settings of the widget
 * @return {Array<string>} the titles
 */
export function tabTitles(settings) {
	return String(settings?.tabs ?? '').split('\n').map(line => line.trim()).filter(line => line !== '')
}

/**
 * The layout of one level of a grid: the widgets on the page itself, or those in one tab.
 *
 * @param {{widgets: Array, layout: Array}} grid the grid
 * @param {string|null} parentKey the key of the tabs widget, or null for the page itself
 * @param {number|null} tab the position of the tab
 * @return {Array} the layout items of that level
 */
export function levelLayout(grid, parentKey = null, tab = null) {
	const level = new Set(grid.widgets
		.filter(widget => (widget.parentKey ?? null) === parentKey && (parentKey === null || widget.parentTab === tab))
		.map(widget => widget.key))
	return grid.layout.filter(item => level.has(item.widgetId))
}

/**
 * The layout with the items of one level replaced by their new positions.
 *
 * @param {Array} layout the whole layout
 * @param {Array} levelItems changed items of one level
 * @return {Array} the whole layout
 */
export function mergeLayout(layout, levelItems) {
	const changed = Object.fromEntries(levelItems.map(item => [item.widgetId, item]))
	return layout.map(item => changed[item.widgetId] ?? item)
}

/**
 * The pages of an application in the order of its page bar: the start page, then the grid
 * pages in the order they were added.
 *
 * @param {object} context an application as the API returns it
 * @return {Array} its pages
 */
export function contextPages(context) {
	const pages = Object.values(context?.pages ?? {})
	return [
		...pages.filter(page => page.page_type === PAGE_TYPE_START),
		...pages.filter(page => page.page_type === PAGE_TYPE_GRID).sort((a, b) => a.id - b.id),
	]
}

/**
 * @param {object} page a page as the API returns it, or as listed in an application
 * @return {boolean} whether it is the start page of its application
 */
export function isStartPage(page) {
	return (page?.pageType ?? page?.page_type) === PAGE_TYPE_START
}

/**
 * @param {object} page a page as the API returns it, or as listed in an application
 * @return {string} its title; the start page is called Home until it gets one
 */
export function pageTitle(page) {
	return page?.title || (isStartPage(page) ? t('tables', 'Home') : '')
}

/**
 * Why a technical name typed for a page cannot be used. An empty name is fine: the server then
 * names the page itself.
 *
 * @param {string} name the name as typed
 * @return {string|null} the reason, or null when the name can be used
 */
export function pageTechnicalNameError(name) {
	if (!name) {
		return null
	}
	if (name.length > 200 || !PAGE_TECHNICAL_NAME_PATTERN.test(name)) {
		return t('tables', 'Use lower case letters, digits and underscores, starting with a letter.')
	}
	if (PAGE_TECHNICAL_NAME_RESERVED.test(name)) {
		return t('tables', 'Names like page_1 are given automatically. Choose another name.')
	}
	return null
}
