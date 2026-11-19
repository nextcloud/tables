/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import CollapsibleDescription from '../../src/modules/main/sections/CollapsibleDescription.vue'
import TableDescription from '../../src/modules/main/sections/TableDescription.vue'

describe('CollapsibleDescription', () => {
	beforeEach(() => {
		// The description is rendered by the editor of the Text app
		window.OCA = { ...window.OCA, Text: { createEditor: () => Promise.resolve({ destroy() {}, setReadOnly() {} }) } }
	})

	it('shows the description behind a button named after its table, whose state is only in aria-expanded', () => {
		cy.mount(CollapsibleDescription, { props: { description: 'What this table is for', title: 'Q&A' } })

		const toggle = () => cy.get('[data-cy="collapsibleDescriptionToggle"]')
		toggle().should('have.attr', 'aria-expanded', 'false').and('contain.text', 'Description of Q&A').and('not.have.attr', 'aria-controls')
		cy.get('.element-description').should('not.exist')
		toggle().click()
		toggle().should('have.attr', 'aria-expanded', 'true').and('contain.text', 'Description of Q&A')
		toggle().invoke('attr', 'aria-controls').then(id => cy.get('#' + id).find('.element-description').should('exist'))

		// A blank description shows nothing
		cy.mount(CollapsibleDescription, { props: { description: '  ' } })
		cy.get('[data-cy="collapsibleDescription"]').should('not.exist')
	})

	it('keeps one panel and one editor when it is opened and closed again', () => {
		const createEditor = cy.stub().resolves({ destroy() {}, setReadOnly() {} })
		window.OCA.Text.createEditor = createEditor
		cy.mount(CollapsibleDescription, { props: { description: 'What this table is for', title: 'Tasks' } })

		const toggle = () => cy.get('[data-cy="collapsibleDescriptionToggle"]')
		toggle().click()
		toggle().invoke('attr', 'aria-controls').then(id => {
			toggle().click()
			cy.get('#' + id).should('exist').and('not.be.visible')
			toggle().click()
			cy.get('.collapsible-description__panel').should('have.length', 1).and('have.id', id).and('be.visible')
		})
		cy.then(() => expect(createEditor).to.have.been.calledOnce)
	})
})

describe('TableDescription', () => {
	it('destroys an editor that is ready after the description left the page', () => {
		let resolveEditor
		const editor = { destroy: cy.stub().as('destroy'), setReadOnly() {} }
		window.OCA = { ...window.OCA, Text: { createEditor: () => new Promise(resolve => { resolveEditor = resolve }) } }
		cy.mount(TableDescription, { props: { description: 'What this table is for', readOnly: true } }).then(({ wrapper }) => {
			wrapper.unmount()
			resolveEditor(editor)
		})
		cy.get('@destroy').should('have.been.calledOnce')
	})
})
