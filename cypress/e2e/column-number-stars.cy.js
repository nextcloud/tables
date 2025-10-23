/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'stars'
const tableTitle = 'Test number stars'

describe('Test column stars', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
		cy.createTable(tableTitle)
	})

	it('Insert and test rows - default values', () => {
		cy.loadTable(tableTitle)
		cy.createNumberStarsColumn(columnTitle, 2, true)

		// insert default value row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('[data-cy="createRowModal"] .stars').contains('★★☆☆☆').should('be.visible')
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('.custom-table table tr td .interactive-stars .star.filled').should('have.length', 2)
		cy.get('.custom-table table tr td .interactive-stars').should('contain', '★').and('contain', '☆')

		// insert row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('.slot button').last().click().click()
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('.custom-table table tr').last().find('.star.filled').should('have.length', 4)

		// insert row
		cy.get('[data-cy="createRowBtn"]').click()
		cy.get('.slot button').first().click().click()
		cy.get('[data-cy="createRowSaveButton"]').click()
		cy.get('.custom-table table tr').last().find('.star.filled').should('have.length', 0)
	})

})
