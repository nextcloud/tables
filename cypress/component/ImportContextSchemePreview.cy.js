/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ImportContextSchemePreview from '../../src/modules/modals/ImportContextSchemePreview.vue'

const MENU_SWITCH = '[data-cy="importSchemeMenu"]'
const SKIPPED = '[data-cy="importSchemeMenuSkipped"]'

const schemeMenu = [
	{ label: 'Dashboard', targetType: 'page', targetPage: 'dashboard', technicalName: 'dashboard' },
	{ label: 'Docs', targetType: 'url', url: 'https://example.org', technicalName: 'docs', parent: 'dashboard' },
]

describe('Import application scheme preview', () => {
	it('sends the menu of the scheme with the import', () => {
		mountPreview({ from: [{ label: 'Old item' }], to: schemeMenu, skipped: [] })
			.then(({ wrapper }) => {
				cy.get(MENU_SWITCH).should('exist')
				cy.contains('Old item').should('exist')
				cy.contains('Docs').should('exist')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper).menuItems).to.deep.equal(schemeMenu)
				})
			})
	})

	it('leaves the menu out when the switch is turned off', () => {
		mountPreview({ from: [], to: schemeMenu, skipped: [] })
			.then(({ wrapper }) => {
				cy.get(MENU_SWITCH).click({ force: true })
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper)).to.not.have.property('menuItems')
				})
			})
	})

	it('lists the items the import would skip', () => {
		mountPreview({ from: [], to: [], skipped: [{ index: 0, label: 'Missing table', reason: 'its table is not here or cannot be managed by the user' }] })

		cy.get(SKIPPED).should('contain', 'Missing table')
	})

	it('has no menu section for a preview without a menu', () => {
		mountPreview(undefined)
			.then(({ wrapper }) => {
				cy.get(MENU_SWITCH).should('not.exist')
				cy.wrap(null).then(() => {
					expect(lastPayload(wrapper)).to.not.have.property('menuItems')
				})
			})
	})
})

/**
 * @param {object|undefined} menuItems the menu part of the preview response
 * @return {Cypress.Chainable} the mount result
 */
function mountPreview(menuItems) {
	return cy.mount(ImportContextSchemePreview, {
		props: {
			previewData: {
				name: { from: 'Intake', to: 'Intake' },
				icon: { from: 'star', to: 'star' },
				description: { from: '', to: '' },
				nodes: { from: [], to: [] },
				addTables: {},
				modifyTables: {},
				...(menuItems === undefined ? {} : { menuItems }),
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
