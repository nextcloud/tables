/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import CollapsibleDescription from '../../src/modules/main/sections/CollapsibleDescription.vue'

describe('CollapsibleDescription', () => {
	beforeEach(() => {
		// The description is rendered by the editor of the Text app
		window.OCA = { ...window.OCA, Text: { createEditor: () => Promise.resolve({ destroy() {}, setReadOnly() {} }) } }
	})

	it('shows the description behind a button that says whether it is open', () => {
		cy.mount(CollapsibleDescription, { props: { description: 'What this table is for' } })

		cy.get('[data-cy="collapsibleDescriptionToggle"]').should('have.attr', 'aria-expanded', 'false').and('contain.text', 'Show description')
		cy.get('.element-description').should('not.exist')
		cy.get('[data-cy="collapsibleDescriptionToggle"]').click()
		cy.get('[data-cy="collapsibleDescriptionToggle"]').should('have.attr', 'aria-expanded', 'true').and('contain.text', 'Hide description')
		cy.get('[data-cy="collapsibleDescriptionToggle"]').invoke('attr', 'aria-controls').then(id => cy.get('#' + id).find('.element-description').should('exist'))

		// A blank description shows nothing
		cy.mount(CollapsibleDescription, { props: { description: '  ' } })
		cy.get('[data-cy="collapsibleDescription"]').should('not.exist')
	})
})
