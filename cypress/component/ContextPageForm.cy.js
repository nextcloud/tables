/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ContextPageForm from '../../src/modules/modals/ContextPageForm.vue'

/**
 * @param {object|null} page the page to edit, or null for a new one
 */
function mountForm(page = null) {
	cy.mount(ContextPageForm, { props: { showModal: false, page } }).then(({ wrapper }) => {
		cy.wrap(wrapper).as('wrapper')
		cy.wrap(wrapper.setProps({ showModal: true }))
	})
}

describe('ContextPageForm', () => {
	it('sends a new page without a technical name when none is typed', () => {
		mountForm()

		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
		cy.get('[data-cy="contextPageTitle"]').type(' Overview ')
		cy.get('[data-cy="contextPageDescription"]').type('All open work')
		cy.get('[data-cy="contextPageSubmit"]').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.equal({ title: 'Overview', icon: '', description: 'All open work' })
		})
	})

	it('refuses a technical name of the wrong form or of the automatic form', () => {
		mountForm()

		cy.get('[data-cy="contextPageTitle"]').type('Overview')
		cy.get('[data-cy="contextPageTechnicalName"]').type('Open work')
		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
		cy.contains('Use lower case letters, digits and underscores, starting with a letter.')
		cy.get('[data-cy="contextPageTechnicalName"]').clear().type('page_12')
		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
		cy.get('[data-cy="contextPageTechnicalName"]').clear().type('open_work')
		cy.get('[data-cy="contextPageSubmit"]').should('be.enabled').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0].technicalName).to.equal('open_work')
		})
	})

	it('keeps the automatic name of a page being edited and does not send it again', () => {
		mountForm({ id: 12, title: 'Overview', icon: 'star', technicalName: 'page_12', description: '' })

		cy.get('[data-cy="contextPageTechnicalName"]').should('have.value', 'page_12')
		cy.get('[data-cy="iconFieldClear"]').click()
		cy.get('[data-cy="contextPageSubmit"]').should('be.enabled').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.equal({ title: 'Overview', icon: '', description: '' })
		})
	})
})
