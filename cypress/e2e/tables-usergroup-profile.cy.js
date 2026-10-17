/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
let referencedUser

describe('Show the profile of a user referenced in a user column', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
		cy.createRandomUser().then(user => {
			referencedUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Opens the profile menu of a user in a cell without starting to edit', () => {
		cy.createTable('User profile table')
		cy.loadTable('User profile table')
		cy.createUsergroupColumn('Assignee', true, false, false, false, [], true)

		cy.contains('button', 'Create row').click()
		cy.get('[data-cy="usergroupRowSelect"] input').type(referencedUser.userId)
		cy.contains('.vs__dropdown-menu li', referencedUser.userId).click()
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="ncTable"] table').contains(referencedUser.userId).should('exist')

		// The avatar menu (hover card on Nextcloud 35+, contacts menu before) is fed by the contacts menu
		cy.intercept('POST', '**/contactsmenu/findOne').as('contactsMenuRequest')
		cy.get('[data-cy="usergroupUserAvatar"] .avatardiv').first().click()
		cy.wait('@contactsMenuRequest')
		cy.get('.v-popper__popper:visible').within(() => {
			cy.get(`a[href*="/u/${referencedUser.userId}"]`).should('exist')
			cy.get('a[href^="mailto:"]').should('exist')
		})
		// Opening the menu must not start inline editing
		cy.get('[data-cy="usergroupCellSelect"]').should('not.exist')
		cy.get('body').type('{esc}')

		// Clicking the name still starts inline editing
		cy.get('.cell-usergroup .usergroup-user__name').first().click()
		cy.get('[data-cy="usergroupCellSelect"]').should('exist')
	})
})
