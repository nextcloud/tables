/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ImportContextSchemePreview from '../../src/modules/modals/ImportContextSchemePreview.vue'

const MENU_SWITCH = '[data-cy="importSchemeMenu"]'
const SKIPPED = '[data-cy="importSchemeMenuSkipped"]'
const NODES_SWITCH = '[data-cy="importSchemeNodes"]'
const TO_ITEMS = '[data-cy="importSchemeMenuTo"]'

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

	it('has no menu section for a preview without a menu', () => {
		mountPreview(undefined)
			.then(({ wrapper }) => {
				cy.get(MENU_SWITCH).should('not.exist')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper)).to.not.have.property('menu')
				})
			})
	})
})

/**
 * @param {object|undefined} menu the menu part of the preview response
 * @param {object} nodes the nodes part of the preview response
 * @return {Cypress.Chainable} the mount result
 */
function mountPreview(menu, nodes = { from: [], to: [] }) {
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
