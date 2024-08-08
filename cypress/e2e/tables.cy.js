/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('The Home Page', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('successfully loads', () => {
		cy.get('.empty-content').contains('Manage data the way you need it.').should('be.visible')
		cy.get('.empty-content__action button').contains('Create new table').should('be.visible')
	})
})
