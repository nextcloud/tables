/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { reactive } from 'vue'
import {
	GRID_BREAKPOINT_MEDIUM,
	GRID_BREAKPOINT_SINGLE,
	GRID_COLUMNS,
	applyNodePositions,
	cloneGrid,
	columnsForWidth,
	defaultConfiguration,
	defaultContent,
	nextFreeRow,
} from '../../src/modules/grid/gridLayout.js'
import { isHexColor } from '../../src/shared/utils/gridColors.js'

const headerType = {
	type: 'header',
	configuration: {
		title: { type: 'string', default: '' },
		showTitle: { type: 'boolean', default: false },
	},
	properties: {
		title: { type: 'string', default: '' },
		textAlign: { type: 'enum', default: 'left' },
		target: { type: 'target', default: null },
	},
}

describe('grid layout', () => {
	it('starts a widget with every configuration and content property at its default', () => {
		expect(defaultConfiguration(headerType)).to.deep.equal({ title: '', showTitle: false })
		expect(defaultContent(headerType)).to.deep.equal({ title: '', textAlign: 'left', target: null })
		expect(defaultContent(null)).to.deep.equal({})
	})

	it('copies a grid deeply, also when it is reactive', () => {
		const stored = reactive({ widgets: [{ id: 'w-1', content: { title: 'Hi' } }], layout: [{ widgetId: 'w-1', gridX: 0 }] })

		const copy = cloneGrid(stored)
		copy.widgets[0].content.title = 'Changed'
		copy.layout[0].gridX = 4

		expect(stored.widgets[0].content.title).to.equal('Hi')
		expect(stored.layout[0].gridX).to.equal(0)
		expect(cloneGrid(undefined)).to.deep.equal({ widgets: [], layout: [] })
	})

	it('places a new widget below the lowest one', () => {
		expect(nextFreeRow([])).to.equal(0)
		expect(nextFreeRow([{ gridY: 0, gridHeight: 2 }, { gridY: 3, gridHeight: 4 }, { gridY: 1 }])).to.equal(7)
	})

	it('shows 12, 6 or 1 columns by canvas width', () => {
		expect(columnsForWidth(GRID_BREAKPOINT_SINGLE - 1)).to.equal(1)
		expect(columnsForWidth(GRID_BREAKPOINT_SINGLE)).to.equal(6)
		expect(columnsForWidth(GRID_BREAKPOINT_MEDIUM - 1)).to.equal(6)
		expect(columnsForWidth(GRID_BREAKPOINT_MEDIUM)).to.equal(GRID_COLUMNS)
	})

	it('applies only the positions of the nodes that changed', () => {
		const layout = [
			{ id: 'w-1', widgetId: 'w-1', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2 },
			{ id: 'w-2', widgetId: 'w-2', gridX: 6, gridY: 0, gridWidth: 6, gridHeight: 2 },
		]

		const updated = applyNodePositions(layout, [{ id: 'w-2', x: 0, y: 2, w: 12, h: 3 }])

		expect(updated[0]).to.equal(layout[0])
		expect(updated[1]).to.deep.equal({ id: 'w-2', widgetId: 'w-2', gridX: 0, gridY: 2, gridWidth: 12, gridHeight: 3 })
	})

	it('accepts only hex colors, like the server', () => {
		expect(isHexColor('#abc')).to.equal(true)
		expect(isHexColor('#a1b2c3')).to.equal(true)
		expect(isHexColor('red')).to.equal(false)
		expect(isHexColor('url(evil)')).to.equal(false)
		expect(isHexColor(null)).to.equal(false)
	})
})
