/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { reactive } from 'vue'
import {
	GRID_BREAKPOINT_MEDIUM,
	GRID_BREAKPOINT_SINGLE,
	GRID_COLUMNS,
	applicationNodeOptions,
	applyNodePositions,
	cloneGrid,
	columnsForWidth,
	gridToWidgets,
	isBlank,
	nextFreeRow,
	nodeTarget,
	orderedNodeRelIds,
	pageToGrid,
	sortedLayout,
	targetTitle,
	trimText,
	widgetTarget,
} from '../../src/modules/grid/gridLayout.js'

const nodes = {
	4: { id: 4, node_type: 0, node_id: 40, permissions: 1 },
	5: { id: 5, node_type: 1, node_id: 50, permissions: 1 },
}

const page = {
	id: 2,
	pageType: 'grid',
	widgets: [
		{ id: 11, type: 'header', title: null, gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 2, settings: { heading: 'Hi', textAlign: 'center' }, source: null },
		{ id: 12, type: 'data', title: 'Tasks', icon: 'check', description: 'Open work', gridX: 0, gridY: 2, gridWidth: 6, gridHeight: 5, settings: [], source: { nodeRelId: 4 } },
		{ id: 13, type: 'data', title: null, gridX: 6, gridY: 2, gridWidth: 6, gridHeight: 5, settings: [], source: { nodeRelId: 5 } },
		{ id: 14, type: 'data', title: null, gridX: 0, gridY: 7, gridWidth: 12, gridHeight: 5, settings: [], source: null },
	],
}

describe('grid layout', () => {
	it('copies a grid deeply, also when it is reactive', () => {
		const stored = reactive({ widgets: [{ key: 'w1', settings: { heading: 'Hi' } }], layout: [{ widgetId: 'w1', gridX: 0 }] })

		const copy = cloneGrid(stored)
		copy.widgets[0].settings.heading = 'Changed'
		copy.layout[0].gridX = 4

		expect(stored.widgets[0].settings.heading).to.equal('Hi')
		expect(stored.layout[0].gridX).to.equal(0)
		expect(cloneGrid(undefined)).to.deep.equal({ widgets: [], layout: [] })
	})

	it('reads a page into widgets with the source of each data widget', () => {
		const grid = pageToGrid(page)

		expect(grid.widgets.map(widget => widget.key)).to.deep.equal(['w11', 'w12', 'w13', 'w14'])
		expect(grid.widgets[0]).to.deep.equal({ key: 'w11', id: 11, type: 'header', title: '', icon: null, description: '', settings: { heading: 'Hi', textAlign: 'center' }, nodeRelId: null, parentKey: null, parentTab: null })
		expect(grid.widgets[1]).to.deep.include({ title: 'Tasks', icon: 'check', description: 'Open work', settings: {}, nodeRelId: 4 })
		expect(grid.layout[2]).to.deep.equal({ widgetId: 'w13', gridX: 6, gridY: 2, gridWidth: 6, gridHeight: 5 })
		expect(widgetTarget(grid.widgets[1], nodes)).to.deep.equal({ type: 'table', id: 40 })
		expect(widgetTarget(grid.widgets[2], nodes)).to.deep.equal({ type: 'view', id: 50 })
		expect(widgetTarget(grid.widgets[3], nodes)).to.equal(null)
		// The table or view left the application
		expect(widgetTarget(grid.widgets[1], { 5: nodes[5] })).to.equal(null)
	})

	it('offers only the tables and views of the application', () => {
		const tables = [{ id: 40, title: 'Tasks', emoji: '✅' }, { id: 41, title: 'Salaries', emoji: '' }]
		const views = [{ id: 50, title: 'Open', emoji: '' }]

		expect(applicationNodeOptions(nodes, tables, views)).to.deep.equal([
			{ id: 4, label: '✅ Tasks' },
			{ id: 5, label: 'Open (view)' },
		])
	})

	it('names the table of a view, keeps titles as typed and skips nodes that are no table or view', () => {
		const tables = [{ id: 40, title: 'Hardware', emoji: '' }, { id: 41, title: 'Software', emoji: '💾' }]
		const views = [{ id: 50, tableId: 40, title: 'Open', emoji: '' }, { id: 51, tableId: 41, title: 'Open', emoji: '' }, { id: 52, tableId: 40, title: 'Stock < 10', emoji: '' }, { id: 53, title: '<Draft> plan', emoji: '' }]
		const applicationNodes = {
			5: { id: 5, node_type: 1, node_id: 50 },
			6: { id: 6, node_type: 1, node_id: 51 },
			7: { id: 7, node_type: 1, node_id: 52 },
			8: { id: 8, node_type: 1, node_id: 53 },
			9: { id: 9, node_type: 9, node_id: 90 },
		}

		expect(applicationNodeOptions(applicationNodes, tables, views)).to.deep.equal([
			{ id: 5, label: 'Open (view of Hardware)' },
			{ id: 6, label: 'Open (view of 💾 Software)' },
			{ id: 7, label: 'Stock < 10 (view of Hardware)' },
			{ id: 8, label: '<Draft> plan (view)' },
		])
	})

	it('titles a table or view with its emoji, and finds the target of a node', () => {
		const tables = [{ id: 40, title: 'Tasks', emoji: '✅' }]
		const views = [{ id: 50, title: 'Open', emoji: '' }]

		expect(targetTitle({ type: 'table', id: 40 }, tables, views)).to.equal('✅ Tasks')
		expect(targetTitle({ type: 'view', id: 50 }, tables, views)).to.equal('Open')
		expect(targetTitle({ type: 'view', id: 40 }, tables, views)).to.equal('')
		expect(nodeTarget({ id: 5, node_type: 1, node_id: 50 })).to.deep.equal({ type: 'view', id: 50 })
		expect(nodeTarget({ id: 9, node_type: 9, node_id: 90 })).to.equal(null)
	})

	it('saves widgets with their id and the node relation they show', () => {
		const grid = pageToGrid(page)
		grid.widgets.push({ key: 'new-1', id: null, type: 'text', title: ' Notes ', icon: 'star', description: ' Read me ', settings: { text: 'A' }, nodeRelId: null })
		grid.layout.push({ widgetId: 'new-1', gridX: 0, gridY: 12, gridWidth: 6, gridHeight: 2 })
		grid.widgets.push({ key: 'unplaced', id: null, type: 'text', title: '', settings: {}, nodeRelId: null })

		const widgets = gridToWidgets(grid)

		expect(widgets).to.have.length(5)
		expect(widgets[0]).to.deep.equal({ id: 11, type: 'header', title: null, icon: null, description: null, gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 2, settings: { heading: 'Hi', textAlign: 'center' }, source: null })
		expect(widgets[1]).to.deep.include({ id: 12, title: 'Tasks', icon: 'check', description: 'Open work', source: { nodeRelId: 4 } })
		expect(widgets[3]).to.deep.include({ id: 14, source: null })
		expect(widgets[4]).to.deep.equal({ type: 'text', title: 'Notes', icon: 'star', description: 'Read me', gridX: 0, gridY: 12, gridWidth: 6, gridHeight: 2, settings: { text: 'A' }, source: null })
	})

	it('keeps the table of a data widget when the widget types did not load', () => {
		const grid = pageToGrid(page)

		const widgets = gridToWidgets(grid)

		expect(widgets[1]).to.deep.include({ id: 12, type: 'data', source: { nodeRelId: 4 } })
	})

	it('reads a text of blanks and zero width spaces as empty, as the server does', () => {
		expect(isBlank('')).to.equal(true)
		expect(isBlank(null)).to.equal(true)
		expect(isBlank(' \u200B\uFEFF\u00A0\u0085\n')).to.equal(true)
		expect(isBlank('\u200BA')).to.equal(false)
		expect(trimText('\u200B Notes \uFEFF')).to.equal('Notes')
		expect(trimText('a\u00A0b')).to.equal('a\u00A0b')
	})

	it('places a new widget below the lowest one', () => {
		expect(nextFreeRow([])).to.equal(0)
		expect(nextFreeRow([{ gridY: 0, gridHeight: 2 }, { gridY: 3, gridHeight: 4 }, { gridY: 1 }])).to.equal(7)
	})

	it('sorts a layout by row, then by column, without changing it', () => {
		const layout = [
			{ widgetId: 'c', gridX: 6, gridY: 2 },
			{ widgetId: 'b', gridX: 6, gridY: 0 },
			{ widgetId: 'a', gridX: 0, gridY: 0 },
		]
		expect(sortedLayout(layout).map(item => item.widgetId)).to.deep.equal(['a', 'b', 'c'])
		expect(layout[0].widgetId).to.equal('c')
	})

	it('orders the nodes as the start page does, then by id', () => {
		const applicationNodes = { 4: {}, 5: {}, 6: {}, 7: {} }
		const content = { 1: { node_rel_id: 4, order: 3 }, 2: { node_rel_id: 6, order: 1 }, 3: { node_rel_id: 5, order: 2 } }
		expect(orderedNodeRelIds(applicationNodes, content)).to.deep.equal([6, 5, 4, 7])
		expect(orderedNodeRelIds(applicationNodes, undefined)).to.deep.equal([4, 5, 6, 7])
	})

	it('shows 12, 6 or 1 columns by canvas width', () => {
		expect(columnsForWidth(GRID_BREAKPOINT_SINGLE - 1)).to.equal(1)
		expect(columnsForWidth(GRID_BREAKPOINT_SINGLE)).to.equal(6)
		expect(columnsForWidth(GRID_BREAKPOINT_MEDIUM - 1)).to.equal(6)
		expect(columnsForWidth(GRID_BREAKPOINT_MEDIUM)).to.equal(GRID_COLUMNS)
	})

	it('applies only the positions of the nodes that changed', () => {
		const layout = [
			{ widgetId: 'w1', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2 },
			{ widgetId: 'w2', gridX: 6, gridY: 0, gridWidth: 6, gridHeight: 2 },
		]

		const updated = applyNodePositions(layout, [{ id: 'w2', x: 0, y: 2, w: 12, h: 3 }])

		expect(updated[0]).to.equal(layout[0])
		expect(updated[1]).to.deep.equal({ widgetId: 'w2', gridX: 0, gridY: 2, gridWidth: 12, gridHeight: 3 })
	})
})
