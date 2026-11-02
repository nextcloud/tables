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
		cy.get('.grid-canvas__stack--editing').should('not.exist')
	})

	it('can be arranged in edit mode on a wide screen', () => {
		cy.viewport(1400, 800)
		mountCanvas({ layout, editable: true })

		cy.get('.grid-canvas__stack--editing').should('exist')
		cy.get('[data-cy="gridNarrowHint"]').should('not.exist')
	})

	it('is only shown, not arranged, on a narrow screen', () => {
		cy.viewport(420, 800)
		mountCanvas({ layout, editable: true })

		cy.get('[data-cy="gridNarrowHint"]').should('be.visible')
		cy.get('.grid-canvas__stack--editing').should('not.exist')
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
})
