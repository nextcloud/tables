/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ref } from 'vue'
import { createPinia } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import TabsWidget from '../../src/modules/grid/widgets/TabsWidget.vue'
import GridLevel from '../../src/modules/grid/GridLevel.vue'
import { gridToWidgets, levelLayout, mergeLayout, newTabKey, pageToGrid, releaseRemovedTabs, widgetTabs } from '../../src/modules/grid/gridLayout.js'

const page = {
	widgets: [
		{ id: 1, type: 'tabs', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 8, settings: { tabs: [{ key: 'open', title: 'Open' }, { key: 'closed', title: 'Closed' }] }, source: null, parentId: null, parentTab: null },
		{ id: 2, type: 'text', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Open ones' }, source: null, parentId: 1, parentTab: 'open' },
		{ id: 3, type: 'text', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 2, settings: { text: 'Closed ones' }, source: null, parentId: 1, parentTab: 'closed' },
		{ id: 4, type: 'header', gridX: 0, gridY: 8, gridWidth: 12, gridHeight: 2, settings: { heading: 'Below' }, source: null, parentId: null, parentTab: null },
	],
}

/**
 * @param {...string} titles titles of tabs
 * @return {Array<{key: string, title: string}>} the tabs, keyed by their titles in lower case
 */
function tabList(...titles) {
	return titles.map(title => ({ key: title.toLowerCase(), title }))
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
	mountInPage(TabsWidget, { widget: grid.widgets[0], editable }, actions)
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
		cy.get('[role="tabpanel"]:visible').should('have.attr', 'aria-labelledby').then(id => cy.get('[role="tab"]').eq(1).should('have.id', id))
		// The first tab stays mounted, hidden, so its widgets keep their state
		cy.get('[role="tabpanel"]').should('have.length', 2)
		cy.contains('Open ones').should('exist').and('not.be.visible')
		cy.get('[role="tab"]').eq(0).click()
		cy.contains('Open ones').should('be.visible')
		cy.get('[role="tab"]').each(tab => cy.get('#' + tab.attr('aria-controls')).should('have.attr', 'role', 'tabpanel'))
	})

	it('names the tab list after the widget and lets its panel scroll with the keyboard', () => {
		const grid = pageToGrid(page)
		mountInPage(TabsWidget, { widget: { ...grid.widgets[0], title: 'Work' }, editable: false }, gridLevel(grid, false))

		cy.get('[role="tablist"]').should('have.attr', 'aria-label', 'Work')
		cy.get('[role="tabpanel"]').should('have.attr', 'tabindex', '0')
	})

	it('tells a viewer that a tab is empty and asks an editor to add widgets to it', () => {
		const grid = pageToGrid({ widgets: [{ ...page.widgets[0], settings: { tabs: [{ key: 'empty', title: 'Empty' }] } }] })
		mountInPage(TabsWidget, { widget: grid.widgets[0], editable: false }, gridLevel(grid, false))
		cy.get('[data-cy="tabsWidgetEmpty"]').should('have.text', 'This tab is empty.')
		cy.get('[data-cy="gridItem"]').should('not.exist')

		mountInPage(TabsWidget, { widget: grid.widgets[0], editable: true }, gridLevel(grid, true))
		cy.get('[data-cy="tabsWidgetEmpty"]').should('have.text', 'Add widgets to this tab.')
		cy.get('[data-cy="tabsWidgetAdd"]').should('be.visible')
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

describe('TabsWidget keyboard', () => {
	it('keeps one tab in the tab order when the tabs change, and leaves Alt with an arrow to the browser', () => {
		const grid = pageToGrid({ widgets: [{ ...page.widgets[0], settings: { tabs: tabList('One', 'Two', 'Three', 'Four') } }] })
		const actions = gridLevel(grid, false)
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
		cy.mount(TabsWidget, {
			props: { widget: grid.widgets[0], editable: false },
			global: { plugins: [createPinia()], mixins: [{ methods: { t, n } }], provide: { gridLevel: actions } },
		}).then(({ wrapper }) => cy.wrap(wrapper).as('wrapper'))

		cy.get('[role="tab"]').eq(0).focus().trigger('keydown', { key: 'End' })
		cy.focused().should('contain.text', 'Four')
		cy.get('@wrapper').then(wrapper => wrapper.setProps({ widget: { ...grid.widgets[0], settings: { tabs: tabList('One', 'Two') } } }))
		cy.get('[role="tab"]').should('have.length', 2)
		cy.get('[role="tab"][tabindex="0"]').should('have.length', 1)

		cy.get('[role="tab"][tabindex="0"]').focus()
		cy.get('[role="tablist"]').then(tablist => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowLeft', altKey: true, bubbles: true, cancelable: true })
			tablist.find('[role="tab"][tabindex="0"]')[0].dispatchEvent(event)
			expect(event.defaultPrevented).to.equal(false)
		})
	})
})

describe('TabsWidget headings', () => {
	it('puts the titles of widgets in a tab one level below the tabs widget, also when that level changes', () => {
		cy.viewport(1400, 800)
		const grid = pageToGrid({ widgets: page.widgets.map(widget => widget.id === 2 ? { ...widget, title: 'Open work' } : widget) })
		const widgetHeadingLevel = ref(2)
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
		cy.mount(TabsWidget, {
			props: { widget: grid.widgets[0], editable: false },
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
		mountInPage(GridLevel, { parentKey: 'w1', tab: 'closed' }, gridLevel(grid, false))

		cy.contains('Closed ones').should('be.visible')
		cy.contains('Open ones').should('not.exist')
		cy.get('[data-cy="gridItem"]').should('have.length', 1)
	})
})

describe('grid levels', () => {
	it('splits the grid by level and saves widgets in tabs with the position of their tabs widget', () => {
		const grid = pageToGrid(page)

		expect(widgetTabs(grid.widgets[0].settings).map(tab => tab.title)).to.deep.equal(['Open', 'Closed'])
		expect(levelLayout(grid).map(item => item.widgetId)).to.deep.equal(['w1', 'w4'])
		expect(levelLayout(grid, 'w1', 'closed').map(item => item.widgetId)).to.deep.equal(['w3'])
		const merged = mergeLayout(grid.layout, [{ widgetId: 'w3', gridX: 6, gridY: 1, gridWidth: 6, gridHeight: 2 }])
		expect(merged[2].gridX).to.equal(6)
		expect(merged[0]).to.equal(grid.layout[0])

		const saved = gridToWidgets(grid, widgetTypes)
		expect(saved[1]).to.deep.include({ parentIndex: 0, parentTab: 'open' })
		expect(saved[2]).to.deep.include({ parentIndex: 0, parentTab: 'closed' })
		expect(saved[0]).not.to.have.property('parentIndex')
	})
	it('moves the widgets of a removed tab onto the page below its widgets, one tab below the other', () => {
		const grid = pageToGrid({
			widgets: [
				...page.widgets,
				{ id: 5, type: 'text', gridX: 6, gridY: 3, gridWidth: 6, gridHeight: 4, settings: { text: 'Closed too' }, source: null, parentId: 1, parentTab: 'closed' },
			],
		})

		expect(releaseRemovedTabs(grid, 'w1', widgetTabs(grid.widgets[0].settings))).to.equal(grid)
		const released = releaseRemovedTabs(grid, 'w1', [])
		expect(released.widgets.filter(widget => widget.parentKey !== null)).to.have.length(0)
		const rows = Object.fromEntries(released.layout.map(item => [item.widgetId, [item.gridX, item.gridY]]))
		// The page ends at row 10; the tab "Open" takes rows 10-11, the tab "Closed" starts at 12
		expect(rows).to.deep.equal({ w1: [0, 0], w2: [0, 10], w3: [0, 12], w4: [0, 8], w5: [6, 15] })
		const renamed = releaseRemovedTabs(grid, 'w1', [{ key: 'closed', title: 'Done' }, { key: 'open', title: 'Open' }])
		expect(renamed.widgets).to.deep.equal(grid.widgets)
	})

	it('makes a new tab key that the server accepts and no other tab has', () => {
		const tabs = [{ key: 'a' }]
		const key = newTabKey(tabs)
		expect(key).to.match(/^[0-9a-f]{12}$/)
		expect(newTabKey([...tabs, { key }])).not.to.equal(key)
	})
})

describe('TabsWidget keyed tabs', () => {
	it('keeps the widgets of a tab and the open tab when the tabs are renamed and reordered', () => {
		cy.viewport(1400, 800)
		const grid = pageToGrid(page)
		cy.intercept('GET', '**/apps/tables/api/2/widget-types', { ocs: { data: [] } })
		cy.mount(TabsWidget, {
			props: { widget: grid.widgets[0], editable: false },
			global: { plugins: [createPinia()], mixins: [{ methods: { t, n } }], provide: { gridLevel: gridLevel(grid, false) } },
		}).then(({ wrapper }) => cy.wrap(wrapper).as('wrapper'))

		cy.get('[role="tab"]').eq(1).click()
		cy.contains('Closed ones', { timeout: 20000 }).should('be.visible')
		cy.get('@wrapper').then(wrapper => wrapper.setProps({
			widget: { ...grid.widgets[0], settings: { tabs: [{ key: 'closed', title: 'Done' }, { key: 'open', title: 'Open' }] } },
		}))
		cy.get('[role="tab"]').eq(0).should('have.text', 'Done').and('have.attr', 'aria-selected', 'true')
		cy.contains('Closed ones').should('be.visible')
		cy.get('[role="tab"]').eq(1).click()
		cy.contains('Open ones').should('be.visible')
	})
})
