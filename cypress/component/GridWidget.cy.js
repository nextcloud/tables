/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import GridWidget from '../../src/modules/grid/GridWidget.vue'
import { useTablesStore } from '../../src/store/store.js'

const nodes = { 4: { id: 4, node_type: 0, node_id: 40, permissions: 1 } }

/**
 * @param {object} widget the widget to show
 * @param {boolean} editable whether the page is edited
 * @param {object|null} layoutItem where the widget sits
 */
function mountWidget(widget, editable, layoutItem = null) {
	cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
	cy.mount(GridWidget, { props: { widget, nodes, editable, layoutItem } }).then(({ wrapper }) => cy.wrap(wrapper).as('wrapper'))
}

describe('GridWidget', () => {
	it('shows the title, icon and description of a widget', () => {
		mountWidget({ key: 'w1', id: 1, type: 'header', title: 'Welcome', icon: 'star', description: 'What we agreed', settings: { heading: 'Projects', subheading: 'This quarter', textAlign: 'end' }, nodeRelId: null }, false)

		cy.get('[data-cy="gridWidget"] h2').should('contain.text', 'Welcome')
		cy.get('[data-cy="gridWidget"] h2 svg').should('exist')
		cy.get('[data-cy="gridWidgetDescription"]').should('have.text', 'What we agreed')
		cy.get('.header-widget').should('contain.text', 'Projects').and('contain.text', 'This quarter').and('have.css', 'text-align', 'end')
	})

	it('shows no heading for a widget without a title, also in edit mode, where its menu floats', () => {
		mountWidget({ key: 'w2', id: 2, type: 'text', title: '', settings: { text: 'First\n\nSecond' }, nodeRelId: null }, false)

		cy.get('[data-cy="gridWidget"] h2').should('not.exist')
		cy.get('.text-widget__paragraph').should('have.length', 2)

		mountWidget({ key: 'w2', id: 2, type: 'text', title: '', settings: { text: 'First' }, nodeRelId: null }, true)
		cy.get('[data-cy="gridWidget"] h2').should('not.exist')
		cy.get('.grid-widget__header--floating button[aria-label="Widget actions"]').should('be.visible')
	})

	it('titles a data widget without a title after its table, which then shows no title of its own', () => {
		cy.intercept('GET', '**/apps/tables/**', { statusCode: 404, body: {} })
		mountWidget({ key: 'w5', id: 5, type: 'data', title: '', settings: {}, nodeRelId: 4 }, false)
		cy.then(() => {
			useTablesStore().tables = [{ id: 40, title: 'Tasks', emoji: '✅' }]
		})

		cy.get('[data-cy="gridWidget"] h2').should('contain.text', '✅ Tasks')
		cy.get('[data-cy="gridWidget"] h1').should('not.exist')
	})

	it('colors a header with a color pair of the theme, and falls back to primary', () => {
		mountWidget({ key: 'w6', id: 6, type: 'header', title: '', settings: { heading: 'Due today', color: 'warning' }, nodeRelId: null }, false)
		cy.get('.header-widget').should('have.class', 'header-widget--warning')

		mountWidget({ key: 'w7', id: 7, type: 'header', title: '', settings: { heading: 'Projects', color: '#ff0000' }, nodeRelId: null }, false)
		cy.get('.header-widget').should('have.class', 'header-widget--primary')
	})

	it('shows every table and view of the application in a tables widget', () => {
		cy.intercept('GET', '**/apps/tables/**', { statusCode: 404, body: {} })
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
		const applicationNodes = { 4: nodes[4], 5: { id: 5, node_type: 1, node_id: 50, permissions: 1 }, 6: { id: 6, node_type: 9, node_id: 60, permissions: 1 } }
		cy.mount(GridWidget, { props: { widget: { key: 'w8', id: 8, type: 'tables', title: '', settings: {}, nodeRelId: null }, nodes: applicationNodes, editable: false } })

		cy.get('[data-cy="tablesWidget"] .data-widget').should('have.length', 2)
	})

	it('shows an empty state in a tables widget of an application without tables', () => {
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
		cy.mount(GridWidget, { props: { widget: { key: 'w9', id: 9, type: 'tables', title: '', settings: {}, nodeRelId: null }, nodes: {}, editable: false } })
		cy.get('[data-cy="tablesWidgetEmpty"]').should('contain.text', 'No tables or views yet')
	})

	it('asks editors to pick a table or view for a data widget without one, and shows viewers an empty state', () => {
		mountWidget({ key: 'w3', id: 3, type: 'data', title: '', settings: {}, nodeRelId: 9 }, true)
		cy.get('[data-cy="dataWidgetPickSource"]').should('be.visible')
		cy.get('[data-cy="dataWidgetNoSource"]').should('not.exist')

		mountWidget({ key: 'w4', id: 4, type: 'data', title: 'Tasks', settings: {}, nodeRelId: null }, false)
		cy.get('[data-cy="dataWidgetNoSource"]').should('contain.text', 'No table or view to show')
		cy.get('[data-cy="dataWidgetPickSource"]').should('not.exist')
	})

	it('moves and resizes a widget from its menu, so the grid can be arranged with a keyboard', () => {
		mountWidget({ key: 'w6', id: 6, type: 'text', title: 'Notes', settings: { text: '' }, nodeRelId: null }, true, { widgetId: 'w6', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2 })

		cy.get('[data-cy="gridWidget"] button[aria-label="Widget actions"]').click()
		cy.get('[data-cy="widgetMoveLeft"]').should('have.class', 'action--disabled')
		cy.get('[data-cy="widgetMoveRight"] button').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('move')[0][1]).to.deep.equal({ gridX: 1 })
		})
	})
})
