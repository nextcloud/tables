/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ref } from 'vue'
import { createPinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import TabsWidget from '../../src/modules/grid/widgets/TabsWidget.vue'
import GridLevel from '../../src/modules/grid/GridLevel.vue'
import { gridToWidgets, levelLayout, mergeLayout, pageToGrid, tabTitles } from '../../src/modules/grid/gridLayout.js'

const page = {
	widgets: [
		{ id: 1, type: 'tabs', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 8, settings: { tabs: 'Open\n\nClosed' }, source: null, parentId: null, parentTab: null },
		{ id: 2, type: 'text', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Open ones' }, source: null, parentId: 1, parentTab: 0 },
		{ id: 3, type: 'text', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Closed ones' }, source: null, parentId: 1, parentTab: 1 },
		{ id: 4, type: 'header', gridX: 0, gridY: 8, gridWidth: 12, gridHeight: 2, settings: { heading: 'Below' }, source: null, parentId: null, parentTab: null },
	],
}

const widgetTypes = [{ type: 'tabs', hasSource: false }, { type: 'text', hasSource: false }, { type: 'header', hasSource: false }]

/**
 * @param {object} grid the grid of the page
 * @param {boolean} editable whether the page is edited
 * @return {object} the grid level the page provides
 */
function gridLevel(grid, editable) {
	return {
		shownLayout: (parentKey = null, tab = null) => levelLayout(grid, parentKey, tab),
		widgetsByKey: () => Object.fromEntries(grid.widgets.map(widget => [widget.key, widget])),
		nodes: () => ({}),
		startPageContent: () => ({}),
		editable: () => editable,
		changeLayout: cy.stub().as('changeLayout'),
		configure: cy.stub(),
		remove: cy.stub(),
		move: cy.stub(),
		addToTab: cy.stub().as('addToTab'),
	}
}

/**
 * @param {object} component the component to mount
 * @param {object} props its props
 * @param {object} actions the grid level the page provides
 */
function mountInPage(component, props, actions) {
	cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
	cy.mount(component, {
		props,
		global: { plugins: [createPinia()], mixins: [{ methods: { t, n } }], provide: { gridLevel: actions } },
	})
}

/**
 * @param {boolean} editable whether the page is edited
 * @return {object} the actions the widget calls
 */
function mountTabs(editable) {
	const grid = pageToGrid(page)
	const actions = gridLevel(grid, editable)
	mountInPage(TabsWidget, { widget: grid.widgets[0], settings: grid.widgets[0].settings, editable }, actions)
	return actions
}

describe('TabsWidget', () => {
	it('shows the widgets of the open tab only', () => {
		cy.viewport(1400, 800)
		mountTabs(false)

		cy.get('[role="tab"]').should('have.length', 2)
		cy.get('[role="tab"]').eq(0).should('have.attr', 'aria-selected', 'true')
		// The widgets in a tab load their component on first use
		cy.contains('Open ones', { timeout: 20000 }).should('be.visible')
		cy.contains('Closed ones').should('not.exist')
		cy.contains('Below').should('not.exist')
		cy.get('[role="tab"]').eq(1).click()
		cy.contains('Closed ones').should('be.visible')
		cy.get('[role="tabpanel"]').should('have.attr', 'aria-labelledby').then(id => cy.get('[role="tab"]').eq(1).should('have.id', id))
	})

	it('moves between tabs with the keyboard with one tab stop', () => {
		mountTabs(false)

		cy.get('[role="tab"][tabindex="0"]').should('have.length', 1).focus()
		cy.focused().trigger('keydown', { key: 'ArrowRight' })
		cy.focused().should('contain.text', 'Closed')
		cy.focused().trigger('keydown', { key: 'Home' })
		cy.focused().should('contain.text', 'Open')
	})
})

describe('TabsWidget headings', () => {
	it('puts the titles of widgets in a tab one level below the tabs widget, also when that level changes', () => {
		cy.viewport(1400, 800)
		const grid = pageToGrid({ widgets: page.widgets.map(widget => widget.id === 2 ? { ...widget, title: 'Open work' } : widget) })
		const widgetHeadingLevel = ref(2)
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
		cy.mount(TabsWidget, {
			props: { widget: grid.widgets[0], settings: grid.widgets[0].settings, editable: false },
			global: { plugins: [createPinia()], mixins: [{ methods: { t, n } }], provide: { gridLevel: gridLevel(grid, false), widgetHeadingLevel } },
		})

		cy.contains('h2', 'Open work', { timeout: 20000 }).should('exist')
		cy.then(() => { widgetHeadingLevel.value = 3 })
		cy.contains('h3', 'Open work').should('exist')
	})
})

describe('GridLevel', () => {
	it('shows the widgets of one tab', () => {
		cy.viewport(1400, 800)
		const grid = pageToGrid(page)
		mountInPage(GridLevel, { parentKey: 'w1', tab: 1 }, gridLevel(grid, false))

		cy.contains('Closed ones').should('be.visible')
		cy.contains('Open ones').should('not.exist')
		cy.get('[data-cy="gridItem"]').should('have.length', 1)
	})
})

describe('grid levels', () => {
	it('splits the grid by level and saves widgets in tabs with the position of their tabs widget', () => {
		const grid = pageToGrid(page)

		expect(tabTitles(grid.widgets[0].settings)).to.deep.equal(['Open', 'Closed'])
		expect(levelLayout(grid).map(item => item.widgetId)).to.deep.equal(['w1', 'w4'])
		expect(levelLayout(grid, 'w1', 1).map(item => item.widgetId)).to.deep.equal(['w3'])
		const merged = mergeLayout(grid.layout, [{ widgetId: 'w3', gridX: 6, gridY: 1, gridWidth: 6, gridHeight: 2 }])
		expect(merged[2].gridX).to.equal(6)
		expect(merged[0]).to.equal(grid.layout[0])

		const saved = gridToWidgets(grid, widgetTypes)
		expect(saved[1]).to.deep.include({ parentIndex: 0, parentTab: 0 })
		expect(saved[2]).to.deep.include({ parentIndex: 0, parentTab: 1 })
		expect(saved[0]).not.to.have.property('parentIndex')
	})
})
