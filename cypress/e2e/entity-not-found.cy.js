/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser

describe('Entity not found error handling', () => {
	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	beforeEach(function() {
		cy.login(localUser)

		cy.intercept('GET', '**/tables/999', { statusCode: 404 }).as('getTable')
		cy.intercept('GET', '**/views/999', { statusCode: 404 }).as('getView')
		cy.intercept('GET', '**/contexts/999*', { statusCode: 404 }).as('getContext')

		cy.visit('apps/tables')
	})

	it('Shows error message when table is not found', () => {
		cy.visit('/apps/tables/#/table/999')

		cy.get('.error-container', { timeout: 10000 })
			.should('contain.text', 'This table could not be found')
	})

	it('Shows error message when view is not found', () => {
		cy.visit('/apps/tables/#/view/999')

		cy.get('.error-container', { timeout: 10000 })
			.should('contain.text', 'This view could not be found')
	})

	it('Shows error message when application is not found', () => {
		cy.visit('/apps/tables/#/application/999')

		cy.get('.error-container', { timeout: 10000 })
			.should('contain.text', 'This application could not be found')
	})
})
