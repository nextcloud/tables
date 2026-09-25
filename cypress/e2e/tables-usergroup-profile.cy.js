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

	it('Shows the profile hover card when hovering a user in a cell', () => {
		cy.createTable('User profile table')
		cy.loadTable('User profile table')
		cy.createUsergroupColumn('Assignee', true, false, false, false, [], true)

		cy.contains('button', 'Create row').click()
		cy.get('[data-cy="usergroupRowSelect"] input').type(referencedUser.userId)
		cy.contains('.vs__dropdown-menu li', referencedUser.userId).click()
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('[data-cy="ncTable"] table').contains(referencedUser.userId).should('exist')

		cy.intercept('GET', '**/ocs/v2.php/profile/**').as('profileRequest')
		cy.intercept('POST', '**/contactsmenu/findOne').as('contactsMenuRequest')
		cy.get('[data-cy="usergroupProfileTrigger"]').first().trigger('mouseenter')
		cy.wait('@profileRequest')
		cy.wait('@contactsMenuRequest')

		cy.get('.profile-hover-card').should('be.visible')
		cy.get('.profile-hover-card').should('contain', referencedUser.userId)
		cy.get('.profile-hover-card a[href^="mailto:"]').should('exist')

		// The cell must still be editable with a click
		cy.get('[data-cy="usergroupProfileTrigger"]').first().trigger('mouseleave')
		cy.get('.cell-usergroup .non-edit-mode').first().click()
		cy.get('[data-cy="usergroupCellSelect"]').should('exist')
	})
})
