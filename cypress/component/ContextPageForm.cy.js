/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ContextPageForm from '../../src/modules/modals/ContextPageForm.vue'

/**
 * @param {object|null} page the page to edit, or null for a new one
 * @param {boolean} pending whether the page is being added or saved
 */
function mountForm(page = null, pending = false) {
	cy.mount(ContextPageForm, { props: { showModal: false, page, pending } }).then(({ wrapper }) => {
		cy.wrap(wrapper).as('wrapper')
		cy.wrap(wrapper.setProps({ showModal: true }))
	})
}

describe('ContextPageForm', () => {
	it('sends a new page without a slug when none is typed', () => {
		mountForm()

		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
		cy.get('[data-cy="contextPageTitle"]').type(' Overview ')
		cy.get('[data-cy="contextPageDescription"]').type('All open work')
		cy.get('[data-cy="contextPageSubmit"]').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.equal({ title: 'Overview', icon: '', description: 'All open work' })
		})
	})

	it('refuses a slug of the wrong form or of the automatic form', () => {
		mountForm()

		cy.get('[data-cy="contextPageTitle"]').type('Overview')
		cy.get('[data-cy="contextPageSlug"]').type('Open work')
		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
		cy.contains('Use lower case letters, digits and underscores, starting with a letter.')
		cy.get('[data-cy="contextPageSlug"]').clear().type('page_12')
		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
		cy.get('[data-cy="contextPageSlug"]').clear().type('open_work')
		cy.get('[data-cy="contextPageSubmit"]').should('be.enabled').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0].slug).to.equal('open_work')
		})
	})

	it('shows the slug of a page being edited without letting it change or sending it', () => {
		mountForm({ id: 12, title: 'Overview', icon: 'star', slug: 'page_12', description: '' })

		cy.get('[data-cy="contextPageSlug"]').should('have.value', 'page_12').and('have.attr', 'readonly')
		cy.get('[data-cy="iconFieldClear"]').click()
		cy.get('[data-cy="contextPageSubmit"]').should('be.enabled').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.equal({ title: 'Overview', icon: '', description: '' })
		})
	})

	it('adds the page with Enter in the title', () => {
		mountForm()

		cy.get('[data-cy="contextPageTitle"]').type('Overview{enter}')
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')).to.have.length(1)
			expect(wrapper.emitted('submit')[0][0].title).to.equal('Overview')
		})
	})

	it('does not send the page again while it is being added', () => {
		mountForm(null, true)

		cy.get('[data-cy="contextPageTitle"]').type('Overview{enter}')
		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')).to.equal(undefined)
		})
	})

	it('marks a wrong slug as invalid', () => {
		mountForm()

		cy.get('[data-cy="contextPageSlug"]').type('Bad Name')
		cy.get('[data-cy="contextPageSlug"]').should('have.attr', 'aria-invalid', 'true')
		cy.get('[data-cy="contextPageSlug"]').clear().type('good_name')
		cy.get('[data-cy="contextPageSlug"]').should('not.have.attr', 'aria-invalid')
	})

	it('saves the start page without a title, so it is called Home', () => {
		mountForm({ id: 3, title: 'Welcome', icon: null, slug: 'page_3', description: '', pageType: 'startpage' })

		cy.contains('Leave empty to call it Home.')
		cy.get('[data-cy="contextPageTitle"]').clear()
		cy.get('[data-cy="contextPageDescription"]').type('Where we start')
		cy.get('[data-cy="contextPageSubmit"]').should('be.enabled').click()
		cy.get('@wrapper').then(wrapper => {
			expect(wrapper.emitted('submit')[0][0]).to.deep.equal({ title: '', icon: '', description: 'Where we start' })
		})
	})

	it('still asks a grid page for a title', () => {
		mountForm({ id: 4, title: 'Overview', icon: null, slug: 'page_4', description: '', pageType: 'page' })

		cy.contains('Leave empty to call it Home.').should('not.exist')
		cy.get('[data-cy="contextPageTitle"]').clear()
		cy.get('[data-cy="contextPageSubmit"]').should('be.disabled')
	})
})
