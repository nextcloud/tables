/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { h } from 'vue'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import GridWidget from '../../src/modules/grid/GridWidget.vue'
import DataWidget from '../../src/modules/grid/widgets/DataWidget.vue'
import TableWrapper from '../../src/modules/main/sections/TableWrapper.vue'
import { useTablesStore } from '../../src/store/store.js'

const nodes = {
	4: { id: 4, node_type: 0, node_id: 40, permissions: 1 },
	5: { id: 5, node_type: 1, node_id: 50, permissions: 1 },
}

const table = { id: 40, title: 'Tasks', emoji: '✅', ownership: 'admin', ownerDisplayName: 'admin', isShared: false, onSharePermissions: null, sort: [] }
const view = { id: 50, tableId: 40, title: 'Open', emoji: '📌', ownership: 'admin', ownerDisplayName: 'admin', isShared: false, onSharePermissions: null, filter: [], sort: [{ columnId: 1, mode: 'DESC' }], columns: [1], columnSettings: [{ columnId: 1, order: 0 }] }
const columns = [{ id: 1, tableId: 40, title: 'Name', type: 'text', subtype: 'line', mandatory: false, description: '', textDefault: '', textAllowedPattern: '', textMaxLength: -1, textUnique: false, selectionOptions: [], selectionDefault: '', datetimeDefault: '' }]
const rows = [{ id: 7, tableId: 40, createdBy: 'admin', createdAt: '2026-01-01 10:00:00', lastEditBy: 'admin', lastEditAt: '2026-01-01 10:00:00', data: [{ columnId: 1, value: 'Write the plan' }] }]

/**
 * Answers the requests of a data widget for the table and the view with one column and one row,
 * and puts both in the store, so the widget renders its table.
 */
function stubData() {
	cy.intercept('GET', '**/apps/tables/api/1/tables/40/columns', columns).as('tableColumns')
	cy.intercept('GET', '**/apps/tables/row/table/40', rows).as('tableRows')
	cy.intercept('GET', '**/apps/tables/api/1/tables/40/relations', {})
	cy.intercept('GET', '**/apps/tables/api/1/views/50/columns', columns).as('viewColumns')
	cy.intercept('GET', '**/apps/tables/row/view/50', rows).as('viewRows')
	cy.intercept('GET', '**/apps/tables/api/1/views/50/relations', {})
	cy.then(() => {
		const store = useTablesStore()
		store.tables = [{ ...table }]
		store.views = [{ ...view }]
	})
}

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
		stubData()
		mountWidget({ key: 'w5', id: 5, type: 'data', title: '', settings: {}, nodeRelId: 4 }, false)

		cy.get('[data-cy="gridWidget"] h2').should('contain.text', '✅ Tasks')
		cy.get('[data-cy="gridWidget"] table').should('contain.text', 'Write the plan')
		cy.get('[data-cy="gridWidget"] h1').should('not.exist')
		// Without the title row, the sticky options of the table sit at the top of the widget
		cy.get('[data-cy="gridWidget"] .options.row').should('have.css', 'top', '0px')
	})

	it('loads the table of a data widget once, also when edit mode toggles', () => {
		stubData()
		mountWidget({ key: 'w5', id: 5, type: 'data', title: '', settings: {}, nodeRelId: 4 }, false)
		cy.get('[data-cy="gridWidget"] table').should('contain.text', 'Write the plan')

		cy.get('@wrapper').then(wrapper => wrapper.setProps({ editable: true }))
		cy.get('[data-cy="gridWidget"] button[aria-haspopup]').should('exist')
		cy.get('@wrapper').then(wrapper => wrapper.setProps({ editable: false }))
		cy.get('[data-cy="gridWidget"] table').should('contain.text', 'Write the plan')
		cy.get('@tableColumns.all').should('have.length', 1)
		cy.get('@tableRows.all').should('have.length', 1)
	})

	it('starts a view in its saved sorting, reloads it when the view changes and starts over on another target', () => {
		stubData()
		mountWidget({ key: 'w10', id: 10, type: 'data', title: '', settings: {}, nodeRelId: 5 }, false)
		cy.get('[data-cy="gridWidget"] table').should('contain.text', 'Write the plan')
		cy.get('@wrapper').should(wrapper => {
			expect(wrapper.findComponent(DataWidget).vm.viewSetting).to.deep.equal({ presetSorting: [{ columnId: 1, mode: 'DESC' }] })
		})

		// As the view settings dialog does after saving a new filter
		cy.then(() => {
			const store = useTablesStore()
			store.views = store.views.map(item => item.id === 50 ? { ...item, filter: [[{ columnId: 1, operator: 'contains', value: 'plan' }]] } : item)
		})
		cy.get('@viewRows.all').should('have.length', 2)
		cy.get('@viewColumns.all').should('have.length', 2)

		cy.get('@wrapper').then(wrapper => wrapper.setProps({ widget: { ...wrapper.props('widget'), nodeRelId: 4 } }))
		cy.get('[data-cy="gridWidget"] table').should('contain.text', 'Write the plan')
		cy.get('@wrapper').should(wrapper => {
			expect(wrapper.findComponent(DataWidget).vm.viewSetting).to.deep.equal({})
		})
	})

	it('opens the sharing and integration tabs of the sidebar for the table of a data widget', () => {
		stubData()
		const opened = []
		const onOpen = data => opened.push(data)
		subscribe('tables:sidebar:sharing', onOpen)
		subscribe('tables:sidebar:integration', onOpen)
		mountWidget({ key: 'w5', id: 5, type: 'data', title: '', settings: {}, nodeRelId: 4 }, false)
		cy.get('[data-cy="gridWidget"] table').should('exist')

		cy.get('@wrapper').then(wrapper => {
			wrapper.findComponent(TableWrapper).vm.$emit('toggle-share')
			wrapper.findComponent(TableWrapper).vm.$emit('show-integration')
		})
		cy.then(() => {
			expect(opened).to.deep.equal([{ open: true, tab: 'sharing' }, { open: true, tab: 'integration' }])
			expect(useTablesStore().activeTableId).to.equal(40)
			unsubscribe('tables:sidebar:sharing', onOpen)
			unsubscribe('tables:sidebar:integration', onOpen)
		})
	})

	it('fits the table of a data widget into the widget instead of the app content', () => {
		cy.viewport(1400, 800)
		stubData()
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
		cy.then(() => document.documentElement.style.setProperty('--app-content-width', '1400px'))
		const widget = { key: 'w5', id: 5, type: 'data', title: '', settings: {}, nodeRelId: 4 }
		cy.mount({ render: () => h('div', { style: 'width: 600px; height: 500px' }, [h(GridWidget, { widget, nodes, editable: false })]) })

		cy.get('[data-cy="gridWidget"] table').should('exist')
		cy.get('.data-widget').then(([dataWidget]) => {
			cy.get('.data-widget .options.row').invoke('width').should('be.at.most', dataWidget.clientWidth)
		})
		cy.then(() => document.documentElement.style.removeProperty('--app-content-width'))
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
