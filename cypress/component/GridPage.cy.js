/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { translate } from '@nextcloud/l10n'
import GridPage from '../../src/modules/grid/GridPage.vue'

// GridPage calls the global t() that the app sets up in its entry point
window.t = translate

const context = { id: 1, name: 'Team board', nodes: {}, pages: {} }

/**
 * @param {object} page the page to show
 */
function mountPage(page) {
	cy.mount(GridPage, { props: { context, page, canEdit: true } }).then(({ wrapper }) => cy.wrap(wrapper).as('wrapper'))
}

describe('GridPage', () => {
	it('gives the focus back to the page actions once the page details close', () => {
		mountPage({ id: 5, page_type: 'grid', title: 'Board', slug: 'board', widgets: [] })

		cy.get('[data-cy="gridPageActions"] button[aria-label="Page actions"]').click()
		cy.get('[data-cy="gridPageEditDetails"]').click()
		cy.get('@wrapper').should(wrapper => expect(wrapper.emitted('edit-details')).to.have.length(1))
		cy.get('@wrapper').then(wrapper => wrapper.vm.focusPageActions())
		cy.focused().should('have.attr', 'aria-label', 'Page actions')
	})
})
