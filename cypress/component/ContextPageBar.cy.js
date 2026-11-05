/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import ContextPageBar from '../../src/modules/grid/ContextPageBar.vue'

const context = {
	id: 1,
	pages: {
		9: { id: 9, page_type: 'grid', title: 'Later', icon: null },
		3: { id: 3, page_type: 'startpage', title: null, icon: null },
		5: { id: 5, page_type: 'grid', title: 'Overview', icon: 'star' },
	},
}

/**
 * @param {object} props props besides the application
 */
function mountBar(props = {}) {
	cy.mount(ContextPageBar, { props: { context, selectedPageId: 3, panelId: 'panel', ...props } })
		.then(({ wrapper }) => cy.wrap(wrapper).as('wrapper'))
}

describe('ContextPageBar', () => {
	it('lists the start page first, then the grid pages in the order they were added', () => {
		mountBar()

		cy.get('[role="tab"]').should('have.length', 3)
		cy.get('[role="tab"]').eq(0).should('have.text', 'Home').and('have.attr', 'aria-selected', 'true')
		cy.get('[role="tab"]').eq(1).should('contain.text', 'Overview').and('have.attr', 'aria-selected', 'false')
		cy.get('[role="tab"]').eq(2).should('contain.text', 'Later')
		cy.get('[role="tab"]').each(tab => expect(tab).to.have.attr('aria-controls', 'panel'))
	})

	it('has one tab stop and moves between tabs with the arrow keys, Home and End', () => {
		mountBar({ selectedPageId: 5 })

		cy.get('[role="tab"][tabindex="0"]').should('have.length', 1).and('contain.text', 'Overview').focus()
		cy.focused().trigger('keydown', { key: 'ArrowRight' })
		cy.focused().should('contain.text', 'Later').and('have.attr', 'tabindex', '0')
		cy.focused().trigger('keydown', { key: 'ArrowRight' })
		cy.focused().should('have.text', 'Home')
		cy.focused().trigger('keydown', { key: 'End' })
		cy.focused().should('contain.text', 'Later')
		cy.focused().trigger('keydown', { key: 'Home' })
		cy.focused().should('have.text', 'Home')
		cy.focused().trigger('keydown', { key: 'ArrowLeft' })
		cy.focused().should('contain.text', 'Later')
		cy.get('@wrapper').then(wrapper => expect(wrapper.emitted('select')).to.equal(undefined))
	})

	it('opens a page on click', () => {
		mountBar()

		cy.get('[role="tab"]').eq(1).click()
		cy.get('@wrapper').then(wrapper => expect(wrapper.emitted('select')[0][0].id).to.equal(5))
	})

	it('offers adding a page only to those who manage the application', () => {
		mountBar()
		cy.get('[data-cy="contextPageAdd"]').should('not.exist')

		mountBar({ canManage: true })
		cy.get('[data-cy="contextPageAdd"]').should('be.visible')
	})
})
