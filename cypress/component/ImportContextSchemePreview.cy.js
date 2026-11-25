/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ImportContextSchemePreview from '../../src/modules/modals/ImportContextSchemePreview.vue'

const MENU_SWITCH = '[data-cy="importSchemeMenu"]'
const SKIPPED = '[data-cy="importSchemeMenuSkipped"]'
const NODES_SWITCH = '[data-cy="importSchemeNodes"]'
const TO_ITEMS = '[data-cy="importSchemeMenuTo"]'
const PAGES_SWITCH = '[data-cy="importSchemePages"]'
const PAGES_SKIPPED = '[data-cy="importSchemePagesSkipped"]'
const PAGES_LIST = '[data-cy="importSchemePagesList"]'

const schemeMenu = [
	{ parent: null, title: 'Reports', displayTitle: 'Reports', icon: null, description: null, target: null },
	{ parent: 0, title: null, displayTitle: 'Docs', icon: null, description: null, target: { url: 'https://example.org' } },
]

describe('Import application scheme preview', () => {
	it('sends the menu of the scheme with the import', () => {
		mountPreview({ from: [{ displayTitle: 'Old item' }], to: schemeMenu, skipped: [] })
			.then(({ wrapper }) => {
				cy.contains('Old item').should('exist')
				cy.contains('Docs').should('exist')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper).menu).to.deep.equal(schemeMenu)
				})
			})
	})

	it('leaves the menu out when the switch is turned off', () => {
		mountPreview({ from: [], to: schemeMenu, skipped: [] })
			.then(({ wrapper }) => {
				cy.get(MENU_SWITCH).click({ force: true })
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper)).to.not.have.property('menu')
				})
			})
	})

	it('sends the menu judged against the current resources when those are not imported', () => {
		const withCurrentNodes = { to: [schemeMenu[0]], skipped: [{ index: 1, title: 'Projects', reason: 'Its table is not part of the application.' }] }
		mountPreview({ from: [], to: schemeMenu, skipped: [], withCurrentNodes })
			.then(({ wrapper }) => {
				cy.get(NODES_SWITCH).click({ force: true })
				cy.get(SKIPPED).should('contain', 'Projects')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper).menu).to.deep.equal(withCurrentNodes.to)
				})
			})
	})

	it('lists the items the import would skip with the reason', () => {
		mountPreview({ from: [], to: [], skipped: [{ index: 0, title: 'Missing table', reason: 'Its table is not part of the application.' }, { index: 1, title: null, reason: 'It is not an object.' }] })

		cy.get(SKIPPED).should('contain', 'Missing table').and('contain', 'Its table is not part of the application.').and('contain', 'Untitled item')
	})

	it('shows what each item opens, with the items of a group in it', () => {
		const to = [
			...schemeMenu,
			{ parent: null, title: 'Projects', displayTitle: 'Projects', icon: null, description: null, target: { url: 'https://projects.example.org' } },
			{ parent: null, title: null, displayTitle: 'Projects', icon: null, description: null, target: { table: 'table-uuid' } },
			{ parent: null, title: null, displayTitle: '', icon: null, description: null, target: { page: 'dashboard' } },
		]
		mountPreview({ from: [], to, skipped: [] }, { from: [], to: [{ node_type: 0, node_uuid: 'table-uuid', node_title: 'Projects' }] })

		cy.get(TO_ITEMS).should('have.length', 4)
		cy.get(TO_ITEMS).eq(0).should('contain', 'Reports').and('contain', 'Group')
		cy.get(TO_ITEMS).eq(0).find('li').should('have.length', 1).and('contain', 'Docs').and('contain', 'Link: https://example.org')
		cy.get(TO_ITEMS).eq(1).should('contain', 'Link: https://projects.example.org')
		cy.get(TO_ITEMS).eq(2).should('contain', 'Table: Projects')
		cy.get(TO_ITEMS).eq(3).should('contain', 'Untitled item').and('contain', 'Page')
	})

	it('sends the pages of the scheme with the import', () => {
		mountPreview(undefined, undefined, { to: schemePages, summary: pageSummary, skipped: [] })
			.then(({ wrapper }) => {
				cy.get(PAGES_SWITCH).should('exist')
				cy.get(PAGES_LIST).should('contain', 'Updates the start page, 0 widgets')
					.and('contain', 'Updates Overview, 1 widget')
					.and('contain', 'New page Reports, 3 widgets, 1 without their table or view')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper).pages).to.deep.equal(schemePages)
				})
			})
	})

	it('leaves the pages out when the switch is turned off', () => {
		mountPreview(undefined, undefined, { to: schemePages, summary: pageSummary, skipped: [] })
			.then(({ wrapper }) => {
				cy.get(PAGES_SWITCH).click({ force: true })
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper)).to.not.have.property('pages')
				})
			})
	})

	it('leaves out the menu items for new pages when the pages are not imported', () => {
		const menu = [
			{ parent: null, title: 'New report', displayTitle: 'New report', target: { page: 'reports' } },
			{ parent: null, title: 'Reports', displayTitle: 'Reports', target: null },
			{ parent: 1, title: 'Overview', displayTitle: 'Overview', target: { page: 'overview' } },
		]
		mountPreview({ from: [], to: menu, skipped: [] }, undefined, { to: schemePages, summary: pageSummary, skipped: [] })
			.then(({ wrapper }) => {
				cy.get(PAGES_SWITCH).click({ force: true })
				cy.get(SKIPPED).should('contain', 'New report: its page is not imported')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper).menu).to.deep.equal([
						{ parent: null, title: 'Reports', displayTitle: 'Reports', target: null },
						{ parent: 0, title: 'Overview', displayTitle: 'Overview', target: { page: 'overview' } },
					])
				})
			})
	})

	it('lists the pages and widgets the import would skip, with the reason', () => {
		mountPreview(undefined, undefined, {
			to: [],
			summary: [],
			skipped: [
				{ pageIndex: 0, page: 'Overview', widgetIndex: 2, widget: 'Elsewhere', reason: 'Unknown widget type "chart"' },
				{ pageIndex: 1, page: 'Second start', widgetIndex: null, widget: null, reason: 'an application has one start page' },
			],
		})

		cy.get(PAGES_SKIPPED).should('contain', 'Overview: Elsewhere: Unknown widget type "chart"').and('contain', 'Second start: an application has one start page')
	})

	it('shows titles with an ampersand or angle brackets as typed', () => {
		mountPreview(undefined, undefined, {
			to: [],
			summary: [{ action: 'add', pageType: 'grid', title: 'R&D overview', currentTitle: null, slug: 'rd', widgetCount: 0, unlinkedWidgetCount: 0 }],
			skipped: [{ pageIndex: 1, page: 'Sales & Marketing <Q1>', widgetIndex: 0, widget: 'A & B', reason: 'its type is not startpage or grid' }],
		})

		cy.get(PAGES_LIST).should('contain', 'New page R&D overview')
		cy.get(PAGES_SKIPPED).should('contain', 'Sales & Marketing <Q1>: A & B')
	})

	it('has no menu section for a preview without a menu', () => {
		mountPreview(undefined)
			.then(({ wrapper }) => {
				cy.get(MENU_SWITCH).should('not.exist')
				cy.get(PAGES_SWITCH).should('not.exist')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper)).to.not.have.property('menu')
					expect(lastPayload(wrapper)).to.not.have.property('pages')
				})
			})
	})
})

const schemePages = [
	{ page_type: 'grid', slug: 'overview', title: 'Overview', widgets: [{ type: 'data', title: 'Tasks', source: { targetType: 'table', targetUuid: 'table-uuid' } }] },
]

const pageSummary = [
	{ action: 'update', pageType: 'startpage', title: null, currentTitle: null, slug: 'dashboard', widgetCount: 0, unlinkedWidgetCount: 0 },
	{ action: 'update', pageType: 'grid', title: 'Overview', currentTitle: 'Overview', slug: 'overview', widgetCount: 1, unlinkedWidgetCount: 0 },
	{ action: 'add', pageType: 'grid', title: 'Reports', currentTitle: null, slug: 'reports', widgetCount: 3, unlinkedWidgetCount: 1 },
]

/**
 * @param {object|undefined} menu the menu part of the preview response
 * @param {object} nodes the nodes part of the preview response
 * @param {object|undefined} pages the pages part of the preview response
 * @return {Cypress.Chainable} the mount result
 */
function mountPreview(menu, nodes = { from: [], to: [] }, pages = undefined) {
	return cy.mount(ImportContextSchemePreview, {
		props: {
			previewData: {
				name: { from: 'Intake', to: 'Intake' },
				icon: { from: 'star', to: 'star' },
				description: { from: '', to: '' },
				nodes,
				addTables: {},
				modifyTables: {},
				...(menu === undefined ? {} : { menu }),
				...(pages === undefined ? {} : { pages }),
			},
		},
	})
}

/**
 * @param {object} wrapper the mounted component
 * @return {object} the last import body the preview emitted
 */
function lastPayload(wrapper) {
	const emitted = wrapper.emitted('update')
	return emitted[emitted.length - 1][0]
}
