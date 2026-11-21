/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { h } from 'vue'
import GridCanvas from '../../src/modules/grid/GridCanvas.vue'

const layout = [
	{ widgetId: 'w1', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 2 },
	{ widgetId: 'w2', gridX: 0, gridY: 2, gridWidth: 6, gridHeight: 3 },
]

/**
 * @param {object} props props of the canvas
 */
function mountCanvas(props) {
	cy.mount(GridCanvas, {
		props,
		slots: { widget: ({ item }) => h('span', { 'data-cy': 'slotWidget' }, item.widgetId) },
	}).then(({ wrapper }) => cy.wrap(wrapper).as('wrapper'))
}

describe('GridCanvas', () => {
	it('places every widget of the layout where it is stored', () => {
		cy.viewport(1400, 800)
		mountCanvas({ layout, editable: false })

		cy.get('[data-cy="gridItem"]').should('have.length', 2)
		cy.get('[data-cy="gridItem"][gs-id="w2"]').should('have.attr', 'gs-w', '6')
		cy.get('[data-cy="slotWidget"]').first().should('have.text', 'w1')
		cy.get('.grid-canvas--editing').should('not.exist')
	})

	it('can be arranged in edit mode on a wide screen', () => {
		cy.viewport(1400, 800)
		mountCanvas({ layout, editable: true })

		cy.get('.grid-canvas--editing').should('exist')
		cy.get('[data-cy="gridNarrowHint"]').should('not.exist')
	})

	it('is only shown, not arranged, on a narrow screen', () => {
		cy.viewport(420, 800)
		mountCanvas({ layout, editable: true })

		cy.get('[data-cy="gridNarrowHint"]').should('be.visible')
		cy.get('.grid-canvas--editing').should('not.exist')
	})

	it('adds a widget that appears in the layout', () => {
		cy.viewport(1400, 800)
		mountCanvas({ layout, editable: true })

		cy.get('@wrapper').then(wrapper => wrapper.setProps({ layout: [...layout, { widgetId: 'w3', gridX: 6, gridY: 2, gridWidth: 6, gridHeight: 3 }] }))
		cy.get('[data-cy="gridItem"]').should('have.length', 3)
		cy.get('@wrapper').should(wrapper => {
			expect(wrapper.vm.grid.engine.nodes.map(node => node.id)).to.include('w3')
		})
	})

	it('keeps the animation GridStack sets on its root when edit mode toggles', () => {
		cy.viewport(1400, 800)
		mountCanvas({ layout, editable: false })

		cy.get('.grid-stack').should('have.class', 'grid-stack-animate')
		cy.get('@wrapper').then(wrapper => wrapper.setProps({ editable: true }))
		cy.get('.grid-canvas--editing').should('exist')
		cy.get('.grid-stack').should('have.class', 'grid-stack-animate')
		cy.get('@wrapper').then(wrapper => wrapper.setProps({ editable: false }))
		cy.get('.grid-canvas--editing').should('not.exist')
		cy.get('.grid-stack').should('have.class', 'grid-stack-animate')
	})

	it('does not animate when the user asks for reduced motion', () => {
		cy.viewport(1400, 800)
		cy.window().then(win => {
			const matchMedia = win.matchMedia.bind(win)
			cy.stub(win, 'matchMedia').callsFake(query => query === '(prefers-reduced-motion: reduce)' ? { matches: true, media: query, addEventListener() {}, removeEventListener() {} } : matchMedia(query))
		})
		mountCanvas({ layout, editable: false })

		cy.get('[data-cy="gridItem"]').should('have.length', 2)
		cy.get('.grid-stack').should('not.have.class', 'grid-stack-animate')
	})

	it('mirrors the grid in a right-to-left language', () => {
		cy.viewport(1400, 800)
		const language = globalThis._nc_l10n_language
		globalThis._nc_l10n_language = 'ar'
		mountCanvas({ layout, editable: false })

		cy.get('.grid-stack').should('have.class', 'grid-stack-rtl')
		cy.then(() => {
			globalThis._nc_l10n_language = language
		})
	})

	it('moves a widget from the menu next to its neighbour without sending it below the others', () => {
		cy.viewport(1400, 800)
		const sideBySide = [
			{ widgetId: 'w1', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 2 },
			{ widgetId: 'w2', gridX: 0, gridY: 2, gridWidth: 6, gridHeight: 3 },
			{ widgetId: 'w3', gridX: 6, gridY: 2, gridWidth: 6, gridHeight: 3 },
			{ widgetId: 'w4', gridX: 0, gridY: 5, gridWidth: 12, gridHeight: 4 },
		]
		mountCanvas({ layout: sideBySide, editable: true })

		cy.get('@wrapper').then(wrapper => wrapper.setProps({ layout: sideBySide.map(item => item.widgetId === 'w2' ? { ...item, gridX: 1 } : item) }))
		cy.get('@wrapper').should(wrapper => {
			const moved = wrapper.vm.grid.engine.nodes.find(node => node.id === 'w2')
			expect(moved.x).to.equal(1)
			expect(moved.y).to.equal(2)
		})
		// GridStack pushed the neighbour to make room, and the page gets told where it went
		cy.get('@wrapper').should(wrapper => {
			const emitted = wrapper.emitted('layout-change')
			expect(emitted).to.have.length(1)
			const settled = emitted[0][0]
			expect(settled.find(item => item.widgetId === 'w2')).to.include({ gridX: 1, gridY: 2 })
			const neighbour = settled.find(item => item.widgetId === 'w3')
			const engineNeighbour = wrapper.vm.grid.engine.nodes.find(node => node.id === 'w3')
			expect(neighbour).to.include({ gridX: engineNeighbour.x, gridY: engineNeighbour.y })
		})
	})

	it('shows the stored layout again when the canvas widens, also after it changed while narrow', () => {
		cy.viewport(800, 800)
		mountCanvas({ layout, editable: false })
		cy.get('.grid-stack').should('have.class', 'gs-6')

		const changed = [
			{ widgetId: 'w1', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 2 },
			{ widgetId: 'w2', gridX: 6, gridY: 4, gridWidth: 4, gridHeight: 2 },
		]
		cy.get('@wrapper').then(wrapper => wrapper.setProps({ layout: changed }))
		cy.viewport(1400, 800)
		cy.get('.grid-stack').should('have.class', 'gs-12')
		cy.get('@wrapper').should(wrapper => {
			const node = wrapper.vm.grid.engine.nodes.find(node => node.id === 'w2')
			expect({ x: node.x, y: node.y, w: node.w, h: node.h }).to.deep.equal({ x: 6, y: 4, w: 4, h: 2 })
		})
	})
})
