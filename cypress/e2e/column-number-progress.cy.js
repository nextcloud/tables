/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
let localUser
const columnTitle = 'progress'
const tableTitle = 'Test number progress'

describe('Test column progress', () => {

	before(function() {
		cy.createRandomUser().then(user => {
			localUser = user
			cy.login(localUser)
		})
	})

	beforeEach(function() {
		cy.login(localUser)
		cy.visit('apps/tables')
	})

	it('Table and column setup', () => {
		cy.createTable(tableTitle)
	})

	it('Insert and test rows - default values', () => {
		cy.loadTable(tableTitle)
		cy.createNumberProgressColumn(columnTitle, 23, true)
		cy.createNumberProgressColumn(columnTitle, null, false)

		// insert default value row
		cy.get('button').contains('Create row').click()
		cy.get('[data-cy="createRowModal"] input[type="number"]').first().should('contain.value', '23')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div progress').first().should('have.value', 23)

		// insert row
		cy.get('button').contains('Create row').click()
		cy.get('[data-cy="createRowModal"] input[type="number"]').last().clear().type('89')
		cy.get('button').contains('Save').click()
		cy.get('.custom-table table tr td div progress').last().should('have.value', 89)
	})

})
