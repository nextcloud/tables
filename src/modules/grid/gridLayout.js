/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
import { isTechnicalNameValid } from '../../shared/utils/columnUtils.js'
import { NODE_TYPE_TABLE, NODE_TYPE_VIEW } from '../../shared/constants.ts'

/** The page type of a grid page, as the API returns it. */
export const PAGE_TYPE_GRID = 'grid'

/** The page type of the start page of an application. */
export const PAGE_TYPE_START = 'startpage'

/** Names of this form are kept for the names the server gives pages itself. */
export const PAGE_SLUG_RESERVED = /^page_\d+$/

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

/** Longest title of a widget, as TextHelper::TITLE_MAX_LENGTH on the server. */
export const TITLE_MAX_LENGTH = 200

/** Longest description of a widget, as TextHelper::DESCRIPTION_MAX_LENGTH on the server. */
export const DESCRIPTION_MAX_LENGTH = 2000

/** Blanks at either end of a text, the ones TextHelper::trim() removes on the server. */
const EDGE_BLANKS = /^[\s\p{Z}\u0085\u200B\uFEFF]+|[\s\p{Z}\u0085\u200B\uFEFF]+$/gu

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
 * A text without blanks at either end, including zero width spaces, so a text of blanks
 * only is empty as it is on the server.
 *
 * @param {string|null|undefined} text the text
 * @return {string} the trimmed text
 */
export function trimText(text) {
	return String(text ?? '').replace(EDGE_BLANKS, '')
}

/**
 * @param {string|null|undefined} text the text
 * @return {boolean} whether the server reads the text as empty
 */
export function isBlank(text) {
	return trimText(text) === ''
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
 * @param {number} pageId id of a page of an application
 * @return {string} the id of its tab in the page bar
 */
export function pageTabId(pageId) {
	return 'context-page-tab-' + pageId
}

/**
 * The layout in reading order: by row, then by column.
 *
 * @param {Array} layout layout items
 * @return {Array} a sorted copy
 */
export function sortedLayout(layout) {
	return [...layout].sort((a, b) => (a.gridY - b.gridY) || (a.gridX - b.gridX))
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
 * The table or view a node of the application stands for.
 *
 * @param {object|null|undefined} node a node of the application, as in context.nodes
 * @return {{type: string, id: number}|null} the target, or null for a node of another type
 */
export function nodeTarget(node) {
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
 * The table or view a data widget shows.
 *
 * @param {object} widget a widget of the grid
 * @param {object} nodes the nodes of the application by relation id, as in context.nodes
 * @return {{type: string, id: number}|null} the target, or null when the widget has no source
 * or its table or view left the application
 */
export function widgetTarget(widget, nodes) {
	return nodeTarget(widget?.nodeRelId ? nodes?.[widget.nodeRelId] : null)
}

/**
 * The emoji and title of a table or view.
 *
 * @param {{type: string, id: number}} target the table or view
 * @param {Array} tables tables known to the store
 * @param {Array} views views known to the store
 * @return {string} the title, empty when the store does not know the table or view
 */
export function targetTitle(target, tables, views) {
	const elements = target?.type === 'view' ? views : tables
	const element = target ? elements.find(element => element.id === target.id) : null
	return element ? [element.emoji, element.title].filter(Boolean).join(' ') : ''
}

/**
 * The tables and views of the application a data widget can show, with their titles. A view
 * also names its table, so views with the same title can be told apart.
 *
 * @param {object} nodes the nodes of the application by relation id
 * @param {Array} tables tables known to the store
 * @param {Array} views views known to the store
 * @return {Array<{id: number, label: string}>} one option per table or view, by relation id
 */
export function applicationNodeOptions(nodes, tables, views) {
	// The select shows labels as text, so they are neither escaped nor sanitized
	const options = { escape: false, sanitize: false }
	return Object.values(nodes ?? {}).flatMap(node => {
		const target = nodeTarget(node)
		if (!target) {
			return []
		}
		const title = targetTitle(target, tables, views) || '#' + target.id
		if (target.type !== 'view') {
			return [{ id: parseInt(node.id), label: title }]
		}
		const tableId = views.find(view => view.id === target.id)?.tableId
		const table = tableId ? targetTitle({ type: 'table', id: tableId }, tables, views) : ''
		return [{
			id: parseInt(node.id),
			label: table
				? t('tables', '{title} (view of {table})', { title, table }, undefined, options)
				: t('tables', '{title} (view)', { title }, undefined, options),
		}]
	})
}

/**
 * The widgets of a grid as the API saves them. A saved widget keeps its id and names the
 * node relation it shows, or null. The source never depends on the loaded widget types,
 * so a save without them keeps the tables of data widgets.
 *
 * @param {{widgets: Array, layout: Array}} grid the grid
 * @return {Array} the widgets to save
 */
export function gridToWidgets(grid) {
	const positions = Object.fromEntries(grid.layout.map(item => [item.widgetId, item]))
	return grid.widgets.filter(widget => positions[widget.key]).map(widget => {
		const { gridX, gridY, gridWidth, gridHeight } = positions[widget.key]
		const saved = {
			type: widget.type,
			title: trimText(widget.title) || null,
			icon: widget.icon || null,
			description: trimText(widget.description) || null,
			gridX,
			gridY,
			gridWidth,
			gridHeight,
			settings: copy(widget.settings ?? {}),
			source: widget.nodeRelId ? { nodeRelId: widget.nodeRelId } : null,
		}
		if (widget.id) {
			saved.id = widget.id
		}
		return saved
	})
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
 * @return {string} its title; the start page is called Home until it gets one, and any
 *                  other page without one goes by its slug
 */
export function pageTitle(page) {
	return page?.title || (isStartPage(page) ? t('tables', 'Home') : (page?.slug ?? ''))
}

/**
 * Why a slug typed for a page cannot be used. An empty name is fine: the server then
 * names the page itself.
 *
 * @param {string} name the name as typed
 * @return {string|null} the reason, or null when the name can be used
 */
export function pageSlugError(name) {
	if (!name) {
		return null
	}
	if (name.length > 200 || !isTechnicalNameValid(name)) {
		return t('tables', 'Use lower case letters, digits and underscores, starting with a letter.')
	}
	if (PAGE_SLUG_RESERVED.test(name)) {
		return t('tables', 'Names like page_1 are given automatically. Choose another name.')
	}
	return null
}
